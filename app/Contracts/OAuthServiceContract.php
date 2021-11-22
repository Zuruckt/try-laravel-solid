<?php

namespace App\Contracts;

interface OAuthServiceContract {
    public function auth(string $code): array;
    public function getUser(string $token): array;
}
