<?php

namespace App\Support;

class AccountPassword
{
    public static function generate(string $email): string
    {
        $name = explode('@', $email)[0];
        $name = implode('.', array_map('ucfirst', explode('.', $name)));
        // The recognizable name is paired with 48 random bits, never a shared suffix.
        return mb_strcut($name, 0, 59, 'UTF-8').'!'.bin2hex(random_bytes(6));
    }
}
