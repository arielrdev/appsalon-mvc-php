<?php

namespace Controllers;

use Classes\Email;
use EmptyIterator;
use Model\Usuario;
use MVC\Router;

    class LoginController {
        public static function login(Router $router) {
            $alertas = [];

            if($_SERVER['REQUEST_METHOD'] === 'POST'){
                $auth = new Usuario($_POST);

                $alertas = $auth->validarLogin();

                if(empty($alertas)) {
                    // Comprobar que exista el usuario
                    $usuario = Usuario::where('email', $auth->email);
                    
                    if($usuario) {
                        // Verificar el password y que este confirmado
                        if($usuario->comprobarPasswordAndVerificado($auth->password)) {
                            //Autenticar el usuario
                            session_start();
                            $_SESSION['id'] = $usuario->id;
                            $_SESSION['nombre'] = $usuario->nombre . " " . $usuario->apellido;
                            $_SESSION['email'] = $usuario->email;
                            $_SESSION['login'] = true;

                            //Redireccionar
                            if($usuario->admin === "1") {
                                $_SESSION['admin'] = $usuario->admin ?? null;
                                header('Location: /admin');
                            }else{
                                header('Location: /cita');
                            }
                            // debuguear($_SESSION);
                        }
                    }else{
                        Usuario::setAlerta('error', 'Usuario no encontrado');
                    }
                }
    
            }

            $alertas = Usuario::getAlertas();
            $router->render('auth/login', ['alertas' => $alertas]);
        }

        public static function logout() {
            session_start();
            $_SESSION = [];
            header('Location: /');
        }

        public static function olvide(Router $router) {
            $alertas = [];

            if($_SERVER['REQUEST_METHOD'] === 'POST') {
                $auth = new Usuario($_POST);
                $alertas = $auth->validarEmail();
                
                if(empty($alertas)) {
                    $usuario = Usuario::where('email', $auth->email);

                    if($usuario && $usuario->confirmado === "1") {
                        //Generar un token
                        $usuario->crearToken();
                        $usuario->guardar(); //Actualiza el registro en la bd

                        //Enviar el email
                        $email = new Email($usuario->nombre, $usuario->email, $usuario->token);
                        $email->enviarInstrucciones();

                        //Alerta de existo
                        Usuario::setAlerta('exito', 'Revisa tu email');
                    
                    }else{
                        Usuario::setAlerta('error', 'El usuario no existe o no esta confirmado');
                    }
                    
                }
            
            }
            $alertas = Usuario::getAlertas();
            $router->render('auth/olvide-password', ['alertas' => $alertas]);
        }

        public static function recuperar(Router $router) {

            $alertas = [];
            $error = false;

            $token = s($_GET['token']);

            //Buscar usuario por su token
            $usuario = Usuario::where('token', $token);

            if(empty($usuario)) {
                Usuario::setAlerta('error', 'Token no válido');
                $error = true;
            }

            if($_SERVER['REQUEST_METHOD'] === 'POST') {
                //Leer el nuevo passwor y guardarlo
                $password = new Usuario($_POST);
                $alertas = $password->validarPassword();

                if(empty($alertas)) {
                    //Eliminar el password anterior
                    $usuario->password = null;
                    
                    //Asignar el nuevo password
                    $usuario->password = $password->password;
                    $usuario->hashPassword();
                    $usuario->token = null;
                    $resultado = $usuario->guardar(); //Update a la BD

                    if($resultado) {
                        header('Location: /');
                    }

                }

            }

            $alertas = Usuario::getAlertas();
            $router->render('auth/recuperar-password', [
                'alertas' => $alertas,
                'error' => $error
            ]);
        }

        public static function crear(Router $router) {
            $usuario = new Usuario;

            //Alertas vacias
            $alertas = [];
            if($_SERVER['REQUEST_METHOD'] === 'POST') {
                $usuario->sincronizar($_POST);
                $alertas = $usuario->validarNuevaCuenta();

                //Revisar que alerta este vacio
                if(empty($alertas)) {
                    //Verificar si el usuario ya esta registrado
                    $resultado = $usuario->existeUsuario();

                    if($resultado->num_rows) {
                        $alertas = Usuario::getAlertas();
                    }else{
                        //Hashear el password
                        $usuario->hashPassword();

                        //Generar un Token unico
                        $usuario->crearToken();

                        //Enviar el email
                        $email = new Email($usuario->nombre, $usuario->email, $usuario->token);
                        $email->enviarConfirmacion();

                        //Crear y guardar en BD el usuario
                        $resultado = $usuario->guardar();
                        if($resultado) {
                            header('Location: /mensaje');

                        }
                    }
                    
                }
                
            }
            $router->render('auth/crear-cuenta', [
                'usuario' => $usuario,
                'alertas' => $alertas
            ]);
        }

        public static function mensaje(Router $router) {
            $router->render('auth/mensaje');

        }

        public static function confirmar(Router $router) {
            $alertas = [];

            $token = s($_GET['token']);
            
            $usuario = Usuario::where('token', $token);
            if(empty($usuario)){
                //Mostrar mensaje de error
                Usuario::setAlerta('error', 'Token no válido');
            }else{
                //Modificar a usuario confirmado
                $usuario->confirmado = "1";
                $usuario->token = null;
                $usuario->guardar();
                Usuario::setAlerta('exito', 'Cuenta confirmada correctamente');

            }
            
            $alertas = Usuario::getAlertas();
            $router->render('auth/confirmar-cuenta', ['alertas' => $alertas]);
        }
    }
?>