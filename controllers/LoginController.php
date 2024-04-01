<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

    class LoginController {
        public static function login(Router $router) {
            $router->render('auth/login');
        }

        public static function logout() {
            echo "Desde Cerrar Sesion";
        }

        public static function olvide(Router $router) {
            $router->render('auth/olvide-password');
        }

        public static function recuperar() {
            echo "Desde recuperar cuenta";
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
            
            $res = Usuario::where('token', $token);
            debuguear($res);
            
            
            $router->render('auth/confirmar-cuenta', ['alertas' => $alertas]);
        }
    }
?>