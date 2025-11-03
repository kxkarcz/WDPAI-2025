<?php

require_once 'AppController.php';
class SecurityController extends AppController{
    private static ?SecurityController $instance = null;
    private function _construct() {}

    public static function getInstance(): SecurityController {
        if (self::$instance === null){
            self::$instance = new SecurityController();
        }
        return self::$instance;
    }

    public function login() {

    // TODO get data from login form
    // check if users is in Database
    // render dashboard after succesfull authentication

        return $this->render("login", ["message" => "Hasło błędne"]);
    }

    public function register() {
        return $this->render('register');
    }
}