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

node {
    println "Jenkins: "
    sh "printenv"

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
}