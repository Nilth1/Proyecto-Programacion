const API = {
    // Si tus archivos PHP están en la raíz web, lo dejamos vacío
    urlBase: '',

    /**
     * Método genérico para realizar peticiones HTTP a la API.
     */
    async request(endpoint, method = 'GET', data = null) {
        const url = this.urlBase + endpoint;

        const opciones = {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin' // Mantiene la cookie de sesión PHP activa entre peticiones
        };

        if (data && method !== 'GET') {
            opciones.body = JSON.stringify(data);
        }

        try {
            const respuesta = await fetch(url, opciones);
            const json = await respuesta.json();
            return json;
        } catch (error) {
            console.error('Error de conexión con la API:', error);
            return { 
                status: 'error', 
                message: 'No se pudo conectar con el servidor de la API.' 
            };
        }
    },

    // ==========================================
    // AUTENTICACIÓN
    // ==========================================

    /**
     * Inicia sesión enviando credenciales a login.php
     */
    async login(email, password, rol = 'socio') {
        return await this.request('/login.php', 'POST', {
            email: email,
            password: password,
            rol: rol
        });
    },

    /**
     * Destruye la sesión activa
     */
    async logout() {
        return await this.request('/logout.php', 'POST');
    }
};