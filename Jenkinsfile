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
properties([parameters([
    string(name: "COMMIT_ID", description: "Commit hash or tag", defaultValue: ""),
    choice(name: "DEPLOY_ENV", description: "Select a deployment environment", choices: [deployEnvChoiceNone, deployEnvChoiceDevelopment, deployEnvChoiceStaging, deployEnvChoiceProduction])
])])


node {
    println "Jenkins: "
    sh "printenv"

    def isPublish = false
    def gitCommit = params.COMMIT_ID
    def deployEnv = params.DEPLOY_ENV

    stage("pre-checkout") {
        abortIfInvalid(gitCommit)
        abortIfInvalid(deployEnv)
    }

    stage("checkout") {
        checkout([
            $class           : "GitSCM",
            branches         : [[name: gitCommit]],
            extensions       : scm.extensions,
            userRemoteConfigs: scm.userRemoteConfigs
        ])
    }

    dir("/blog") {
        docker.image("danielbraga/php-devcontainer:latest").inside() {
            sh "echo '==> Starting pipeline in ${env.WORKSPACE} ...'"

            def isProduction = deployEnv == deployEnvChoiceProduction
            stage("dependencies") {
                try {
                    if (isProduction) {
                        sh "composer install --no-dev --no-progress"
                    } else {
                        sh "composer install --no-progress"
                    }
                    sh "composer dump-autoload"
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Error in dependencies installation for the project (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }
        }
    }
}