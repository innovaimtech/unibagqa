<?php
session_start(); // Iniciar sesión

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id_contacto"])) {
    $_SESSION["id_contacto"] = $_POST["id_contacto"]; // Guardar en sesión
    echo "ID guardado en sesión: " . htmlspecialchars($_SESSION["id_contacto"]);
} else {
    echo "No se recibió ningún ID.";
}
?>
