#!/usr/bin/env groovy

def isValid(String param) {
    return param != null && param.trim() != ""
}

def abortIfInvalid(String param) {
    if (!isValid(param)) {
        currentBuild.result = "ABORTED"
        error("The input parameters are invalid");
    }
}

def sshFormat(String user, String host, String port, String... commands) {
    def command = commands.join("; ")
    return "ssh -o StrictHostKeyChecking=no ${user}@${host} -p ${port} '${command}'"
}

def runSshKeyGen(String host) {
    sh("ssh-keygen -R ${host}")
}

def deployDockerComposeFileName = "blog.yml"
def deployDirectory = "/data/docker/blog"
def deployEnvChoiceNone = "NONE"
def deployEnvChoiceDevelopment = "DEVELOPMENT"
def deployEnvChoiceStaging = "STAGING"
def deployEnvChoiceProduction = "PRODUCTION"
def deployEnvPropertyFileIdDevelopment = "jenkinsdemo-build-config-development"
def deployEnvPropertyFileIdStaging = "jenkinsdemo-build-config-staging"
def deployEnvPropertyFileIdProduction = "jenkinsdemo-build-config-production"

properties([parameters([
    string(name: "COMMIT_ID", description: "Commit hash or tag", defaultValue: ""),
    choice(name: "DEPLOY_ENV", description: "Select a deployment environment", choices: [deployEnvChoiceNone, deployEnvChoiceDevelopment, deployEnvChoiceStaging, deployEnvChoiceProduction])
])])

node {
    println "Jenkins: "
    sh "printenv"

    def projectVendor = ""
    def projectName = ""
    def registryCredential = ""
    def hostToDeploy = ""
    def hostPortToDeploy = ""
    def userToDeploy = ""
    def isPublish = false
    def gitCommit = params.COMMIT_ID
    def deployEnv = params.DEPLOY_ENV
    def deployProperties = [:] as Map<String, String>

    stage("pre-checkout") {
        abortIfInvalid(gitCommit)
        abortIfInvalid(deployEnv)

        if (deployEnv == deployEnvChoiceDevelopment) {
            deployEnvPropertyFileId = deployEnvPropertyFileIdDevelopment
        } else if (deployEnv == deployEnvChoiceStaging) {
            deployEnvPropertyFileId = deployEnvPropertyFileIdStaging
        } else if (deployEnv == deployEnvChoiceProduction) {
            deployEnvPropertyFileId = deployEnvPropertyFileIdProduction
        }

        isPublish = deployEnv != deployEnvChoiceNone
        if (isPublish) {
            abortIfInvalid(deployEnvPropertyFileId)
            configFileProvider([configFile(fileId: deployEnvPropertyFileId, variable: "configFile")]) {
                deployProperties = readProperties file: "$configFile"
                  projectVendor = deployProperties.PROJECT_VENDOR
                  projectName = deployProperties.PROJECT_NAME
                  registryCredential = deployProperties.REGISTRY_CREDENTIAL
                  hostToDeploy = deployProperties.HOST
                  hostPortToDeploy = deployProperties.PORT
                  userToDeploy = deployProperties.USER

                dockerTag = gitCommit + "_" + deployEnv
                deployProperties["DOCKER_IMAGE_TAG"] = dockerTag
            }

            abortIfInvalid(projectVendor)
            abortIfInvalid(projectName)
            abortIfInvalid(registryCredential)
            abortIfInvalid(hostToDeploy)
            abortIfInvalid(hostPortToDeploy)
            abortIfInvalid(userToDeploy)
        }
    }

    stage("checkout") {
        checkout([
            $class           : "GitSCM",
            branches         : [[name: gitCommit]],
            extensions       : scm.extensions,
            userRemoteConfigs: scm.userRemoteConfigs
        ])
    }

    dir("blog") {
        docker.image("danielbraga/php-devcontainer:latest").inside() {
            sh "echo '==> Starting pipeline in ${env.WORKSPACE} ...'"

            stage("dependencies") {
                try {
                    sh "composer install --no-progress"
                    sh "composer dump-autoload"
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Error in dependencies installation (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }

            stage('lint') {
                try {
                    sh 'find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l'
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Syntax check returned an error (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }

            stage('checkstyle') {
                try {
                    sh 'rm -rf build/logs'
                    sh 'mkdir -p build/logs'
                    sh 'vendor/bin/phpcs --report=checkstyle --report-file=build/logs/checkstyle.xml --standard=phpcs.xml --extensions=php --ignore=vendor/ . || exit 0'
                    recordIssues enabledForFailure: true, tool: checkStyle(pattern: '**/build/logs/checkstyle.xml')
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Checkstyle report generation returned an error (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }  

            stage('tests') {
                try {
                    sh 'XDEBUG_MODE=coverage vendor/bin/phpunit -v --coverage-cobertura build/logs/cobertura.xml'
                    cobertura coberturaReportFile: '**/build/logs/cobertura.xml'
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] PHPUnit tests returned an error (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }  
        }
    }

    if (isPublish) {
        def dockerImage
        stage("build docker") {
            try {
                sh "rm -rf blog/build"
                sh "rm -rf build/tmp"
                sh "mkdir -p build/tmp"
                sh "cp -R docker/* build/tmp"
                sh "cp -R blog build/tmp"
                dockerImage = docker.build("${projectVendor}/${projectName}:" + dockerTag, "build/tmp")
            } catch (err) {
                slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Erro ao realizar build da imagem docker do frontend (${BUILD_URL}).", tokenCredentialId: "slack-token")
                throw err
            }
        }

        stage("publish docker") {
            try {
                docker.withRegistry("", registryCredential) {
                    dockerImage.push dockerTag
                }
            } catch (err) {
                slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Error publishing Docker image (${BUILD_URL}).", tokenCredentialId: "slack-token")
                throw err
            }
        }

        def proceedDeploy = true
        if (deployEnv != deployEnvChoiceDevelopment) {
            stage("should i deploy now?") {
                try {
                    slackSend(color: "warning", message: "[ ${JOB_BASE_NAME} ] To apply changes in ${deployEnv} access the following address in the next 10 minutes: ${JOB_URL}", tokenCredentialId: "slack-token")
                    timeout(time: 10, unit: "MINUTES") {
                        input(id: "Deploy Gate", message: "Deploy in ${deployEnv}?", ok: "Deploy")
                    }
                } catch (err) {
                    println(err)
                    slackSend(color: "warning", message: "[ ${JOB_BASE_NAME} ] Changes in ${deployEnv} have not been applied.", tokenCredentialId: "slack-token")
                    proceedDeploy = false
                }
            }
        }

        if (proceedDeploy) {
            stage("deploy") {
                def dockerComposeFullPathInServer = "${deployDirectory}/${deployDockerComposeFileName}" as String

                try {
                    runSshKeyGen(hostToDeploy)
                    runSsh(
                        userToDeploy,
                        hostToDeploy,
                        hostPortToDeploy,
                        "sudo mkdir -p /data/docker/blog" as String,
                        "sudo rm -f ${dockerComposeFullPathInServer}.backup" as String,
                        "sudo cp ${dockerComposeFullPathInServer} ${dockerComposeFullPathInServer}.backup" as String,
                        "sudo chmod -R 777 /data/docker/blog"
                    )
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Error connecting to host '${hostToDeploy}' (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }
        }
    }
}
