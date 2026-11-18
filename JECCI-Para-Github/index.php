<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>JECCI | Sistema de Inventario</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 15% 20%, #174b72 0, transparent 35%),
                linear-gradient(135deg, #061525, #0c2945, #071a2e);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 25px;
        }

        .contenedor {
            width: 100%;
            max-width: 1000px;
            min-height: 590px;
            display: flex;
            background: white;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0,0,0,.45);
            animation: aparecer .8s ease;
        }

        @keyframes aparecer {
            from {
                opacity: 0;
                transform: translateY(25px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* LADO IZQUIERDO */

        .lado-izquierdo {
            width: 50%;
            background: linear-gradient(145deg, #09233d, #10466c);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 45px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .lado-izquierdo::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 50%;
            top: -130px;
            left: -130px;
        }

        .lado-izquierdo::after {
            content: "";
            position: absolute;
            width: 250px;
            height: 250px;
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 50%;
            bottom: -120px;
            right: -100px;
        }

        .logo {
            width: 105px;
            height: 105px;
            background: linear-gradient(135deg, #ff444b, #c91425);
            border-radius: 28px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 52px;
            margin-bottom: 28px;
            box-shadow: 0 12px 30px rgba(227,38,46,.35);
            transform: rotate(-5deg);
            z-index: 1;
        }

        .lado-izquierdo h1 {
            font-size: 39px;
            letter-spacing: 5px;
            margin-bottom: 10px;
            z-index: 1;
        }

        .linea {
            width: 65px;
            height: 4px;
            background: #e3262e;
            border-radius: 5px;
            margin: 12px 0 18px;
            z-index: 1;
        }

        .empresa {
            font-size: 14px;
            letter-spacing: 2px;
            color: #d9e7f2;
            z-index: 1;
        }

        .descripcion {
            margin-top: 35px;
            line-height: 1.8;
            color: #c6d7e5;
            font-size: 16px;
            z-index: 1;
        }

        .etiqueta {
            margin-top: 35px;
            padding: 10px 20px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 30px;
            font-size: 12px;
            color: #dceafa;
            z-index: 1;
        }

        /* LADO DERECHO */

        .lado-derecho {
            width: 50%;
            padding: 65px 55px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .bienvenida {
            color: #e3262e;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .lado-derecho h2 {
            color: #09233d;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .subtitulo {
            color: #87919c;
            font-size: 14px;
            margin-bottom: 35px;
        }

        .campo {
            margin-bottom: 23px;
        }

        .campo label {
            display: block;
            margin-bottom: 9px;
            font-weight: 600;
            font-size: 14px;
            color: #26394d;
        }

        .entrada {
            position: relative;
        }

        .entrada input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 1px solid #dce3ea;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 14px;
            outline: none;
            transition: .3s;
        }

        .entrada input:focus {
            border-color: #1769aa;
            background: white;
            box-shadow: 0 0 0 4px rgba(23,105,170,.10);
        }

        .icono {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #71849a;
            font-size: 17px;
        }

        .ver {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            cursor: pointer;
            color: #71849a;
            font-size: 12px;
        }

        .recordar {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 28px;
            color: #64748b;
            font-size: 13px;
        }

        .recordar input {
            accent-color: #e3262e;
            width: 15px;
            height: 15px;
        }

        .boton {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #e3262e, #b91827);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            cursor: pointer;
            transition: .3s;
            box-shadow: 0 8px 20px rgba(227,38,46,.20);
        }

        .boton:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(227,38,46,.35);
        }

        .pie {
            text-align: center;
            margin-top: 35px;
            color: #a0aab5;
            font-size: 12px;
            line-height: 1.8;
        }

        .pie span {
            color: #e3262e;
            font-weight: bold;
        }

        /* CELULARES */

        @media(max-width: 700px) {
            body {
                padding: 15px;
            }

            .contenedor {
                flex-direction: column;
                max-width: 430px;
            }

            .lado-izquierdo,
            .lado-derecho {
                width: 100%;
            }

            .lado-izquierdo {
                padding: 35px 20px;
            }

            .logo {
                width: 75px;
                height: 75px;
                font-size: 38px;
                margin-bottom: 15px;
            }

            .lado-izquierdo h1 {
                font-size: 30px;
            }

            .descripcion {
                margin-top: 15px;
            }

            .etiqueta {
                margin-top: 15px;
            }

            .lado-derecho {
                padding: 40px 28px;
            }

            .lado-derecho h2 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>

<div class="contenedor">

    <div class="lado-izquierdo">

        <div class="logo">⚙</div>

        <h1>JECCI</h1>

        <div class="linea"></div>

        <p class="empresa">SOLUCIONES INDUSTRIALES</p>

        <p class="descripcion">
            Sistema inteligente de<br>
            Gestión de Inventario
        </p>

        <div class="etiqueta">
            CONTROL • ORGANIZACIÓN • EFICIENCIA
        </div>

    </div>

    <div class="lado-derecho">

        <p class="bienvenida">BIENVENIDO A JECCI</p>

        <h2>Iniciar sesión</h2>

        <p class="subtitulo">
            Ingresa tus datos para acceder al sistema
        </p>

        <form action="dashboard.php" method="GET">

            <div class="campo">

                <label>Usuario</label>

                <div class="entrada">
                    <span class="icono">👤</span>

                    <input
                        type="text"
                        name="usuario"
                        placeholder="Ingrese su usuario"
                        autocomplete="username"
                        required
                    >
                </div>

            </div>

            <div class="campo">

                <label>Contraseña</label>

                <div class="entrada">
                    <span class="icono">🔒</span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingrese su contraseña"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="ver"
                        onclick="mostrarPassword()"
                    >
                        VER
                    </button>
                </div>

            </div>

            <div class="recordar">

                <input type="checkbox" id="recordar">

                <label for="recordar">Recordar usuario</label>

            </div>

            <button class="boton" type="submit">
                INGRESAR →
            </button>

        </form>

        <div class="pie">
            <span>JECCI</span> Soluciones Industriales<br>
            © 2026 - Sistema de Gestión de Inventario
        </div>

    </div>

</div>

<script>
    function mostrarPassword() {
        const campo = document.getElementById("password");
        const boton = document.querySelector(".ver");

        if (campo.type === "password") {
            campo.type = "text";
            boton.textContent = "OCULTAR";
        } else {
            campo.type = "password";
            boton.textContent = "VER";
        }
    }
</script>

</body>
</html>