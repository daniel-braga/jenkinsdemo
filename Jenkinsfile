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

    dir("blog") {
        docker.image("danielbraga/php-devcontainer:latest").inside() {
            sh "echo '==> Starting pipeline in ${env.WORKSPACE} ...'"

            stage("dependencies") {
                try {
                    sh "composer install --no-progress"
                    sh "composer dump-autoload"
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Error in dependencies installation for the project (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }

            stage('lint') {
                try {
                    sh 'find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l'
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Syntax check returned an error for the project (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }

            stage('checkstyle') {
                try {
                    sh 'vendor/bin/phpcs -v --report=checkstyle --report-file=../build/logs/checkstyle.xml --standard=phpcs.xml --extensions=php --ignore=vendor/ . || exit 0'
                    recordIssues enabledForFailure: true, tool: checkStyle(pattern: '**/build/logs/checkstyle.xml')
                } catch (err) {
                    slackSend(color: "error", message: "[ ${JOB_BASE_NAME} ] [ FAIL ] Checkstyle report generation returned an error for the project (${BUILD_URL}).", tokenCredentialId: "slack-token")
                    throw err
                }
            }  
        }
    }
}
