// Validación básica del lado del cliente (el servidor valida de nuevo en controller.php)
const form = document.getElementById("form-login");
const mensajeError = document.getElementById("mensaje-error");
const campoClave = document.getElementById("contra");
const botonClave = document.getElementById("toggle-clave");

function mostrarError(texto) {
  mensajeError.textContent = texto;
  mensajeError.hidden = false;
}

form.addEventListener("submit", function (e) {
  const kronos = form.kronos.value.trim();
  const contra = form.contra.value.trim();

  if (kronos === "" || contra === "") {
    e.preventDefault();
    mostrarError("Los campos están vacíos");
    return;
  }

  mensajeError.hidden = true;
});

// Mostrar u ocultar la contraseña
botonClave.addEventListener("click", function () {
  const estabaOculta = campoClave.type === "password";

  campoClave.type = estabaOculta ? "text" : "password";
  botonClave.textContent = estabaOculta ? "Ocultar" : "Mostrar";
  botonClave.setAttribute("aria-label", estabaOculta ? "Ocultar contraseña" : "Mostrar contraseña");
  campoClave.focus();
});