<?php
$pw="typeYourPassword";
$secretKey="get your secret key, stored in ~/.ssh/secret.key";
echo openssl_encrypt($pw, "aes-256-cbc", $secretKey);