<?php

require_once 'AppController.php';
class SecurityController extends AppController{
    public function login() {

    // TODO get data from login form
    // heck if users is in Database
    // render dashboard after succesfull authentication

        return $this->render("login", ["message" => "Hasło błędne"]);
    }

    public function register() {
        return $this->render('register');
    }
}