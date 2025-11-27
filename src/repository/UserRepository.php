<?php

require_once 'Repository.php';

class UserRepository extends Repository {

    public function getUsers(): ?array {
        $query = $this->database->connect()->prepare('
            SELECT * FROM "LABusers"
        ');

        $query->execute();
        $users = $query->fetchAll(PDO::FETCH_ASSOC);

        return $users;
    }
}
