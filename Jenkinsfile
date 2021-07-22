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
            configFileProvider([configFile(fileId: deployEnvPropertyFileId, variable: "configFile")]) {
                deployProperties = readProperties file: "$configFile"
                  projectVendor = deployProperties.PROJECT_VENDOR
                  projectName = deployProperties.PROJECT_NAME

                dockerTag = gitCommit + "_" + deployEnv
                deployProperties["DOCKER_IMAGE_TAG"] = dockerTag
            }

            abortIfInvalid(projectVendor)
            abortIfInvalid(projectName)
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
        stage("build-docker") {
            try {
                sh "rm -rf blog/build"
                sh "rm -rf build/tmp"
                sh "mkadir -p build/tmp"
                sh "cp -R docker/* build/tmp"
                sh "cp -R blog build/tmp"
                dockerImage = docker.build("${projectVendor}/${projectName}:" + dockerTag, "build/temp")
            } catch (err) {
                slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Erro ao realizar build da imagem docker do frontend (${BUILD_URL}).", tokenCredentialId: "slack-token")
                throw err
            }
        }
    }
}
