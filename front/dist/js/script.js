// script.js - JavaScript común de las páginas públicas (inicio, planes, contacto, entrenadores).
// El login de verdad está en pages/login.html (usa api.js). Esto solo actúa si la página tiene un #loginForm,
// así no tira "Cannot read properties of null" en las páginas que no lo tienen.
const formLogin = document.getElementById('loginForm');
if (formLogin && !window.API) {
    formLogin.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const response = await fetch('/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value
                })
            });
            const result = await response.json();
            if (result.status === 'success') window.location.href = '/dist/pages/' + result.data.rol + '.html';
            else alert(result.message);
        } catch (error) {
            console.error('Error en la petición:', error);
        }
    });
}
