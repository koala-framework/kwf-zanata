To use zanta you will need a user and api-token.
Please add it to a config file in your home directory:

    ~/.config/koala-framework/kwf-zanata/config

It should look like this:

    {
        "user": "yourUserName",
        "apiToken": "yourApiKey"
    }

composer.json extra entry should look like this:

    ...
    "extra": {
        "kwf-zanata": {
            "project": "my-project",
            "version": "2",
            "docId": "resourceFile",
            "restApiUrl": "https://translate.zanata.org/rest"
        },
        ...
    },
    ...

+ docId is the name of the uploaded translation-file.
+ Use restApiUrl to access custom deployed zanata instance
