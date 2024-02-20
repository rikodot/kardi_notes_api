<?php

  //https://github.com/wanttobeno/Study_Diffie_Hellman_Key_Exchange/blob/master/main.c
class DH {
  static $p = 2147483647;
  static $g = 5;

  //calc a * b % p, avoid 64bit overflow
  static function mul_mod_p($a, $b) {
    $res = 0;
    $a %= DH::$p;
    while ($b > 0) {
      if ($b % 2 == 1) {
        $res = ($res + $a) % DH::$p;
      }
      $a = ($a * 2) % DH::$p;
      $b = (int)($b / 2);
    }
    return $res;
  }

  //calc a ^ b % p, avoid 64bit overflow
  static function pow_mod_p($a, $b) {
    $res = 1;
    if ($a > DH::$p)
      $a%=DH::$p;
    while ($b > 0) {
      if ($b % 2 == 1) {
        $res = DH::mul_mod_p($res, $a);
      }
      $a = DH::mul_mod_p($a, $a);
      $b = (int)($b / 2);
    }
    return $res;
  }

  static function test() {
    $client_priv = rand(0, 2147483647);
    $server_priv = rand(0, 2147483647);

    $client_pub = DH::pow_mod_p(DH::$g, $client_priv);
    $server_pub = DH::pow_mod_p(DH::$g, $server_priv);

    $client_calc = DH::pow_mod_p($server_pub, $client_priv);
    $server_calc = DH::pow_mod_p($client_pub, $server_priv);

    print("client_priv: $client_priv");
    print("server_priv: $server_priv");
    print("client_pub: $client_pub");
    print("server_pub: $server_pub");
    print("client_calc: $client_calc");
    print("server_calc: $server_calc");
  }

  static function gen_random($final_key, $length = 32, $shift = 76)
  {
    $allowed_chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $random_string = "";
    for ($i = 0; $i < $length; ++$i)
    {
        if ($final_key - $shift < 0) { $final_key = DH::mul_mod_p($final_key, $shift); }
        else { $final_key -= $shift; }
        $random_string .= $allowed_chars[$final_key % strlen($allowed_chars)];
    }
    return $random_string;
  }

  static function enc($data, $key, $iv)
  {
    return openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
  }

  static function dec($data, $key, $iv)
  {
    return openssl_decrypt($data, 'aes-256-cbc', $key, 0, $iv);
  }
}