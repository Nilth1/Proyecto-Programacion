document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = {
        email: document.getElementById('email').value,
        password: document.getElementById('password').value
    };

    try {
        // Apunta al archivo .php en la raíz del front
        const response = await fetch('/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok) {
            localStorage.setItem('token', result.token);
            window.location.href = '/dashboard.html';
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error en la petición:', error);
    }
});