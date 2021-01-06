<?php // see https://github.com/jimmybear217/serverExplorer

    // application settings
    $settings = array(
        "explorer" => array(
            "enabled" => true,
            "display_errors" => true,
            "use_remote_assets" => true,
            "assets_server" => "https://jimmybear217.dev/projects/repo/server_explorer/assets"
        ),
        "auth" => array(
            "require_auth" => true,
            "ip_whitelist" => array(
                "enabled" => true,
                "authorised_ips" => array("127.0.0.1")
            ),
            "user_password" => array(
                "enabled" => false,
                "server" => "https://jimmybear217.dev/projects/repo/server_explorer/userAuthServer.php"
            ),
            "app_password" => array(
                "enabled" => true,
                "hash" => password_hash("SuperSecurePassword", PASSWORD_DEFAULT)
            ),
            "2FA" => array(
                "enabled" => false,
                "server" => "https://jimmybear217.dev/projects/repo/server_explorer/2fa.php"
            ),
            "DemoMode" => array(
                "enabled" => true,
                "username" => "",
                "password" => "SuperSecurePassword"
            )
        )
    );

    // logs
    if ($settings["explorer"]["display_errors"]) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }

    // remote assets configuration
    $remote_assets = array(
        "favicon" => array(
            "actual" => $settings["explorer"]["assets_server"] . '/serverExplorer.png',
            "backup" => 'https://github.com/favicon.ico'
        ),
        "stylesheet" => $settings["explorer"]["assets_server"] . '/style.css',
        "logo" => $settings["explorer"]["assets_server"] . '/serverExplorer.png'
    );

    // pages content
    $pages = array(
        "camouflage"    => '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">'
                        . '<html><head><title>404 Not Found</title></head><body>'
                        . '<h1>Not Found</h1><p>The requested URL was not found on this server.</p>'
                        . ((!empty($_SERVER["SERVER_SIGNATURE"])) ? '<hr>' . $_SERVER["SERVER_SIGNATURE"] : "" )
                        . '</body></html>',
        "header"        => '<DOCTYPE HTML><html><head><title>Server Explorer</title><link rel="icon" src="'
                        . (($settings["explorer"]["use_remote_assets"]) ? $remote_assets["favicon"]["actual"] : $remote_assets["favicon"]["backup"])
                        . '">' . (($settings["explorer"]["use_remote_assets"]) ? '<link rel="stylesheet" src="' . $remote_assets["stylesheet"] . '">' : '')
                        . '</head><body><header><h1>' . (($settings["explorer"]["use_remote_assets"]) ? '<img src="' . $remote_assets["logo"] . '" height="32" width="32"> ' : '') . 'Server Explorer</h1></header>'
                        . '<div id="output"><pre>',
        "input"         => '</pre></div><div id="input"><form action="' . $_SERVER["PHP_SELF"] . '?action=submit" method="POST">'
                        . '<input name="command" type="text" placeholder="$>"><input type="submit" value="send (or press enter)">'
                        . '</form>',
        "login"         => 'Please log in</pre><div id="login"><form action="' . $_SERVER["PHP_SELF"] . '?action=login" method="POST">'
                        . (($settings["auth"]["user_password"]["enabled"]) ? '<input name="username" placeholder="username" type="text" autocomplete="username" value="' . (($settings["auth"]["DemoMode"]["enabled"]) ? $settings["auth"]["DemoMode"]["username"] : "") . '">' : "")
                        . (($settings["auth"]["user_password"]["enabled"] || $settings["auth"]["app_password"]["enabled"]) ? '<input name="password" placeholder="password" type="password" autocomplete="username" value="' . (($settings["auth"]["DemoMode"]["enabled"]) ? $settings["auth"]["DemoMode"]["password"] : "") . '">' : "")
                        . '<input value="login (or press enter)" type="submit">',
        "footer"        => '</body></html>'
    );


    // check if system is enabled
    if (!$settings["explorer"]["enabled"]) {
        http_response_code(404);
        die($pages["camouflage"]);
    }

    // check if authentification is enabled
    if ($settings["auth"]["require_auth"]) {
        $login_state = false;
        $login_nextstep = "input";
        // check whitelist
        if ($settings["auth"]["ip_whitelist"]["enabled"] && !$login_state) {
            if (!in_array($_SERVER["REMOTE_ADDR"], $settings["auth"]["ip_whitelist"]["authorised_ips"])) {
                http_response_code(404);
                die ($pages["camouflage"]);
            }
        }
        if ($settings["auth"]["user_password"]["enabled"] && !$login_state) {
            // check user password
            if (isset($_GET["action"]) && $_GET["action"] == "login") {
                if (!empty($_POST["username"]) && !empty($_POST["password"])) {
                    $username = filter_var($_POST["username"], FILTER_SANITIZE_STRING);
                    $ch = curl_init($settings["auth"]["user_password"]["server"] . "?action=checkUsrPwd");
                    curl_setopt_array($ch, array(
                        "CURLOPT_RETURNTRANSFER" => true,
                        "CURLOPT_HEADER" => false,
                        "CURLOPT_HTTPHEADER" => array("Content-Type: multipart/form-data"),
                        "CURLOPT_POST" => true,
                        "CURLOPT_POSTFIELDS" => array("username" => $username, "password" => $_POST["password"])
                    ));
                    if (intval(curl_exec($ch)) == 1) {
                        $token = base64_encode($username . ":" . password_hash($username . date("Y", time()) . $_SERVER["SCRIPT_FILENAME"], PASSWORD_DEFAULT));
                        setcookie("auth_user", $token, time()+(60*60), $_SERVER["PHP_SELF"]);
                        $login_state = true;
                    }
                }
            } else if (in_array("auth_user", array_keys($_COOKIE))) {
                // check password token
                $token = explode(":", base64_decode($_COOKIE["auth_user"]));
                if (password_verify($token[0] . date("Y", time()) . $_SERVER["SCRIPT_FILENAME"], $token[1])) {
                    $login_state = true;
                } else {
                    setcookie("auth_user", "", time() - (60*60));
                    $login_state = false;
                }
            }
        } else if ($settings["auth"]["app_password"]["enabled"] && !$login_state) {
            // check app password
            if (isset($_GET["action"]) && $_GET["action"] == "login") {
                if (!empty($_POST["password"])) {
                    if (password_verify($_POST["password"], $settings["auth"]["app_password"]["hash"])) {
                        $token = password_hash("app" . date("Y", time()) . $_SERVER["SCRIPT_FILENAME"], PASSWORD_DEFAULT);
                        setcookie("auth_user", $token, time()+(60*60), $_SERVER["PHP_SELF"]);
                        $login_state = true;
                    }
                }
            } else if (in_array("auth_app", array_keys($_COOKIE))) {
                // check password token
                $token = "app" . date("Y", time()) . $_SERVER["SCRIPT_FILENAME"];
                if (password_verify($token, $_COOKIE["auth_app"])) {
                    $login_state = true;
                } else {
                    setcookie("auth_app", "", time() - (60*60));
                    $login_state = false;
                }
            }
        }
        if ($login_nextstep == "input" && $login_state) {
            $login_nextstep = "ok";
        }
        // check 2FA
        if ($settings["auth"]["2FA"]["enabled"] && $login_state){
            if (in_array("auth_2fa", array_keys($_COOKIE))) {
                // check 2FA token
                if (intval(file_get_contents($settings["auth"]["2FA"]["server"] . "?action=check&token=" . $_COOKIE["auth_2fa"])) != 1) {
                    $login_state = false;
                }
            } else {
                // send 2FA request
                $token = file_get_contents($settings["auth"]["2FA"]["server"] . "?action=submit");
                if (!empty($token) && strlen($token) < 300 && strlen($token) > 30) {
                    setcookie("auth_2fa", $token, time()+(60*60), $_SERVER["PHP_SELF"]);
                    $login_nextstep = "2fa";
                }
            }
        }
        if (!$login_state) {
            if ($settings["auth"]["user_password"]["enabled"] || $settings["auth"]["app_password"]["enabled"]) {
                die ($pages["header"] . $pages["login"] . $pages["footer"]);
            } else if ($settings["auth"]["2FA"]["enabled"]) {
                http_response_code(404);
                die ($pages["camouflage"]);
            }
        }
    }

    // write header
    echo $pages["header"];
    

    // interpret commands
    if (isset($_GET["action"]) && $_GET["action"] == "submit" && !empty($_POST["input"])) {
        echo "Command: " . $_POST["input"];
    }
    
    
    // write footer
    echo $pages["input"] . $pages["footer"];