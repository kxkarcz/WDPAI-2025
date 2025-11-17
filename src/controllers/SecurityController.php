<?php

require_once 'AppController.php';
class SecurityController extends AppController{
    private static ?SecurityController $instance = null;
private static array $users = [
    [
        'email' => 'anna@example.com',
        'password' => '$2y$10$wz2g9JrHYcF8bLGBbDkEXuJQAnl4uO9RV6cWJKcf.6uAEkhFZpU0i', // test123
        'first_name' => 'Anna',
        'surname' => 'Kowalska',
        'birthdate' => '1995-04-12'
    ],
    [
        'email' => 'bartek@example.com',
        'password' => '$2y$10$fK9rLobZK2C6rJq6B/9I6u6Udaez9CaRu7eC/0zT3pGq5piVDsElW', // haslo456
        'first_name' => 'Bartek',
        'surname' => 'Nowak',
        'birthdate' => '1990-11-03'
    ],
    [
        'email' => 'celina@example.com',
        'password' => '$2y$10$Cq1J6YMGzRKR6XzTb3fDF.6sC6CShm8kFgEv7jJdtyWkhC1GuazJa', // qwerty
        'first_name' => 'Celina',
        'surname' => 'Wiśniewska',
        'birthdate' => '2000-07-21'
    ],
];


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
        if ($this->isPost()){
            $email = $_POST['email'];
            $password = $_POST['password'];
            if (empty($email) || empty($password)) {
                return $this->render('login', ['messages' => 'Fill all fields']);
            }
             $userRow = null;
            foreach (self::$users as $u) {
                if (strcasecmp($u['email'], $email) === 0) {
                    $userRow = $u;
                    break;
                }
            }

            if (!$userRow) {
                return $this->render('login', ['messages' => 'User not found']);
            }
            if (!password_verify($password, $userRow['password'])) {
                return $this->render('login', ['messages' => 'Wrong password']);
            }


            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/dashboard");
            //return $this->render('dashboard');
        }

        //return $this->render("login", ["message" => "Hasło błędne"]);
    }

    public function register()
    {
        if (!$this->isPost()) {
            return $this->render('register');
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $firstName = $_POST['firstname'] ?? '';
        $surname = $_POST['surname'] ?? '';
        $birthdate = $_POST['birthdate'] ?? '';

        if (empty($email) || empty($password) || empty($password2)
            || empty($firstName) || empty($surname) || empty($birthdate)) {
            return $this->render('register', ['messages' => 'Fill all fields']);
        }

        if ($password !== $password2) {
            return $this->render('register', ['messages' => 'Passwords do not match']);
        }

	    foreach (self::$users as $u) {
            if (strcasecmp($u['email'], $email) === 0) {
                return $this->render('register', ['messages' => 'Email is taken']);
            }
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    self::$users[] = [
        'email' => $email,
        'password' => $hashedPassword,
        'first_name' => $firstName,
        'surname' => $surname,
        'birthdate' => $birthdate
    ];

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/login");
    }
}

}