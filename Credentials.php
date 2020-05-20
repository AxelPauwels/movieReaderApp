<?php

declare(strict_types=1);

namespace MovieReaderApp;

class Credentials
{
    // X_RAPIDAPI CREDENTIALS
    // ----------------------
    const X_RAPIDAPI_HOST = "x-rapidapi-host: ***";
    const X_RAPIDAPI_KEY = "x-rapidapi-key: ***";


    // SERVER CREDENTIALS
    // ------------------

    // movies.***.be [***]
    const DB_WEBSITE_TARGET = "***";// custom name to identify which website will be updated
    const DB_SERVERNAME = "***";
    const DB_USERNAME = "***";
    const DB_PASSWORD = "***";
    const DB_NAME = "***";
    const DB_PORT = 3307;
    const DB_SSH_TUNNEL_REQUIRED = false; // set this true if this is a connection to vagrant or raspberryPi

    // movies.local [Vragrant]
    // Note: Before this works: 1. run vagrant up | 2. Add sshKey to ~/.ssh/config | 3. make a ssh tunnel (here in PHP)
//    const DB_WEBSITE_TARGET = "***"; // custom name to identify which website will be updated
//    const DB_SERVERNAME = "127.0.0.1";
//    const DB_USERNAME = "***";
//    const DB_PASSWORD = "***";
//    const DB_NAME = "***";
//    const DB_PORT = 3307; // is tunneled
//    const DB_SSH_TUNNEL_REQUIRED = true; // set this true if this is a connection to vagrant or raspberryPi

    // movieserver.local [Vragrant]
    // Note: Before this works: 1. run vagrant up | 2. Add sshKey to ~/.ssh/config | 3. make a ssh tunnel (here in PHP)
//    const DB_WEBSITE_TARGET = "***"; // custom name to identify which website will be updated
//    const DB_SERVERNAME = "127.0.0.1";
//    const DB_USERNAME = "***";
//    const DB_PASSWORD = "***";
//    const DB_NAME = "***";
//    const DB_PORT = 3307; // is tunneled
//    const DB_SSH_TUNNEL_REQUIRED = true; // set this true if this is a connection to vagrant or raspberryPi

}