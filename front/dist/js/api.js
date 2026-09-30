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
    },

    // ==========================================
    // PANEL DE ADMINISTRADOR
    // ==========================================

    /** Trae usuarios, entrenadores, rutinas y finanzas de una sola vez */
    async adminTodo() {
        return await this.request('/admin.php?accion=todo');
    },

    /**
     * Ejecuta una acción del admin:
     * crear_persona | quitar_persona | agregar_ejercicio | quitar_ejercicio
     */
    async adminAccion(accion, datos = {}) {
        return await this.request('/admin.php?accion=' + accion, 'POST', datos);
    },

    // ==========================================
    // PANEL DE SOCIO
    // ==========================================

    /** Trae plan, rutinas, clases, entrenadores y quejas del socio logueado */
    async socioTodo() {
        return await this.request('/socio.php?accion=todo');
    },

    /**
     * Ejecuta una acción del socio:
     * anotar_clase | cancelar_clase | escribir_queja | borrar_queja |
     * comprar_plan | guardar_objetivo | elegir_entrenador
     */
    async socioAccion(accion, datos = {}) {
        return await this.request('/socio.php?accion=' + accion, 'POST', datos);
    },

    // ==========================================
    // PANEL DE ENTRENADOR
    // ==========================================

    /** Trae ejercicios, rutinas y alumnos (con sus objetivos y rutinas asignadas) */
    async entrenadorTodo() {
        return await this.request('/entrenador.php?accion=todo');
    },

    /**
     * Ejecuta una acción del entrenador:
     * crear_ejercicio | borrar_ejercicio | crear_rutina | borrar_rutina |
     * asignar_rutina | quitar_asignacion
     */
    async entrenadorAccion(accion, datos = {}) {
        return await this.request('/entrenador.php?accion=' + accion, 'POST', datos);
    },

    /** Avisa que el usuario logueado está en línea (llamar cada ~1 min desde cualquier panel) */
    async ping() {
        return await this.request('/admin.php?accion=ping', 'POST', {});
    }
};