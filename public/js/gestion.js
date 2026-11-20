(() => {
    const nombre = document.getElementById('nombre');
    const slug = document.querySelector('[data-slug-sugerido]');
    if (!nombre || !slug) return;

    let manual = slug.dataset.slugManual === 'true';
    const sugerir = () => {
        if (manual) return;
        slug.value = nombre.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
            .slice(0, 150).replace(/-+$/g, '');
    };
    slug.addEventListener('input', () => { manual = true; });
    nombre.addEventListener('input', sugerir);
    sugerir();
})();

// Calendario propio: el formato visible no depende del idioma del navegador.
(() => {
    const controles = [];
    const pad = (valor, longitud = 2) => String(valor).padStart(longitud, '0');
    const fechaUTC = (ano, mes, dia) => {
        const fecha = new Date(0);
        fecha.setUTCHours(0, 0, 0, 0);
        fecha.setUTCFullYear(ano, mes - 1, dia);
        return fecha;
    };
    const isoDia = fecha => `${pad(fecha.getUTCFullYear(), 4)}-${pad(fecha.getUTCMonth() + 1)}-${pad(fecha.getUTCDate())}`;
    const parsear = (texto, hora) => {
        const partes = texto.match(hora ? /^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/ : /^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!partes) return null;
        const [, dia, mes, ano, hh = '00', mm = '00'] = partes;
        if (+ano < 1 || +hh > 23 || +mm > 59) return null;
        const fecha = fechaUTC(+ano, +mes, +dia);
        if (fecha.getUTCFullYear() !== +ano || fecha.getUTCMonth() + 1 !== +mes || fecha.getUTCDate() !== +dia) return null;
        return { fecha, dia: isoDia(fecha), iso: isoDia(fecha) + (hora ? `T${hh}:${mm}` : ''), hh, mm };
    };
    const desdeISO = valor => fechaUTC(+valor.slice(0, 4), +valor.slice(5, 7), +valor.slice(8, 10));
    const obtener = nombre => controles.find(control => control.nombre === nombre);
    const actualizar = () => controles.forEach(control => control.validar(control.tocado));
    const nombreDia = new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });

    document.querySelectorAll('[data-fecha-mexicana]').forEach(raiz => {
        const texto = raiz.querySelector('[data-fecha-texto]');
        const oculto = raiz.querySelector('[data-fecha-iso]');
        const abrir = raiz.querySelector('[data-fecha-abrir]');
        const calendario = raiz.querySelector('.fecha-calendario');
        const dias = raiz.querySelector('[data-calendario-dias]');
        const mes = raiz.querySelector('[data-calendario-mes]');
        const ano = raiz.querySelector('[data-calendario-ano]');
        const hora = raiz.querySelector('[data-calendario-hora]');
        const minuto = raiz.querySelector('[data-calendario-minuto]');
        const error = raiz.querySelector('.fecha-error');
        const conHora = raiz.dataset.conHora === 'true';
        let cursor = desdeISO(raiz.dataset.hoy);
        let focoDia = '';
        const control = {
            nombre: raiz.dataset.campo, original: raiz.dataset.original, tocado: false,
            leer: () => parsear(texto.value.trim(), conHora),
            restriccion(iso) {
                const inicio = obtener('fecha_inicio');
                const fin = obtener('fecha_fin');
                if (control.nombre === 'cierre_inscripciones_at') {
                    if (iso !== control.original && iso < raiz.dataset.minCierre) return 'El cierre debe ser posterior a la hora del servidor indicada al abrir el formulario.';
                    const final = fin?.leer()?.dia;
                    if (final && (iso !== control.original || final !== fin.original) && iso.slice(0, 10) > final) return 'El cierre no puede superar las 23:59 del día final.';
                } else {
                    if (iso !== control.original && iso < raiz.dataset.hoy) return 'La fecha nueva debe ser hoy o una fecha futura.';
                    const inicial = inicio?.leer()?.dia;
                    if (control.nombre === 'fecha_fin' && inicial && (iso !== control.original || inicial !== inicio.original) && iso < inicial) return 'La fecha final debe ser igual o posterior a la inicial.';
                }
                return '';
            },
            validar(mostrar = false) {
                const valor = control.leer();
                let mensaje = '';
                if (texto.value.trim() !== '' && !valor) mensaje = conHora ? 'Escribe dd/mm/aaaa HH:mm con una hora de 00:00 a 23:59.' : 'Escribe una fecha válida en formato dd/mm/aaaa.';
                else if (!valor && texto.required) mensaje = 'Este campo es obligatorio.';
                else if (valor) mensaje = control.restriccion(valor.iso);
                oculto.value = valor?.iso ?? '';
                texto.setCustomValidity(mensaje);
                error.textContent = mensaje;
                error.hidden = !mostrar || !mensaje;
                if (mostrar) texto.setAttribute('aria-invalid', mensaje ? 'true' : 'false');
                if (!calendario.hidden) dibujar();
            }
        };
        controles.push(control);

        const valorParaDia = dia => {
            if (!conHora) return dia;
            const actual = control.leer();
            if (actual?.iso === control.original && actual.dia === dia) return actual.iso;
            let reloj = `${actual?.hh ?? '00'}:${actual?.mm ?? '00'}`;
            if (dia === raiz.dataset.minCierre.slice(0, 10) && reloj < raiz.dataset.minCierre.slice(11)) reloj = raiz.dataset.minCierre.slice(11);
            return `${dia}T${reloj}`;
        };
        const disponible = fecha => !control.restriccion(valorParaDia(isoDia(fecha)));
        const cerrar = (devolverFoco = true) => {
            calendario.hidden = true;
            calendario.querySelectorAll('input, select').forEach(campo => { campo.disabled = true; });
            abrir.setAttribute('aria-expanded', 'false');
            if (devolverFoco) abrir.focus();
        };
        const elegir = iso => {
            texto.value = `${iso.slice(8, 10)}/${iso.slice(5, 7)}/${iso.slice(0, 4)}` + (conHora ? ` ${iso.slice(11)}` : '');
            control.tocado = true;
            if (conHora) { hora.value = +iso.slice(11, 13); minuto.value = +iso.slice(14, 16); }
            actualizar();
        };
        const dibujar = () => {
            const anio = cursor.getUTCFullYear();
            const mesNumero = cursor.getUTCMonth() + 1;
            mes.value = mesNumero;
            ano.value = anio;
            const primero = fechaUTC(anio, mesNumero, 1);
            const total = fechaUTC(anio, mesNumero + 1, 0).getUTCDate();
            const fragmento = document.createDocumentFragment();
            for (let indice = 0; indice < (primero.getUTCDay() + 6) % 7; indice++) fragmento.append(document.createElement('span'));
            const botones = [];
            for (let dia = 1; dia <= total; dia++) {
                const fecha = fechaUTC(anio, mesNumero, dia);
                const iso = isoDia(fecha);
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.textContent = dia;
                boton.dataset.dia = iso;
                boton.disabled = !disponible(fecha);
                boton.tabIndex = -1;
                boton.setAttribute('aria-label', nombreDia.format(fecha));
                boton.setAttribute('aria-pressed', String(control.leer()?.dia === iso));
                if (iso === raiz.dataset.hoy) boton.setAttribute('aria-current', 'date');
                boton.addEventListener('click', () => {
                    focoDia = iso;
                    elegir(valorParaDia(iso));
                    if (!conHora) cerrar();
                    else hora.focus();
                });
                boton.addEventListener('keydown', evento => {
                    const semana = (fecha.getUTCDay() + 6) % 7;
                    const saltos = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7, Home: -semana, End: 6 - semana };
                    let destino;
                    if (Object.hasOwn(saltos, evento.key)) destino = fechaUTC(anio, mesNumero, dia + saltos[evento.key]);
                    else if (evento.key === 'PageUp' || evento.key === 'PageDown') {
                        const otroMes = mesNumero + (evento.key === 'PageUp' ? -1 : 1);
                        destino = fechaUTC(anio, otroMes, Math.min(dia, fechaUTC(anio, otroMes + 1, 0).getUTCDate()));
                    } else return;
                    evento.preventDefault();
                    if (destino.getUTCFullYear() < 1 || destino.getUTCFullYear() > 9999 || !disponible(destino)) return;
                    cursor = destino;
                    focoDia = isoDia(destino);
                    dibujar();
                    dias.querySelector(`[data-dia="${focoDia}"]`)?.focus();
                });
                botones.push(boton);
                fragmento.append(boton);
            }
            const entrada = botones.find(boton => !boton.disabled && boton.dataset.dia === focoDia)
                ?? botones.find(boton => !boton.disabled && boton.dataset.dia === control.leer()?.dia)
                ?? botones.find(boton => !boton.disabled);
            if (entrada) entrada.tabIndex = 0;
            dias.replaceChildren(fragmento);
            raiz.querySelector('[data-calendario-anuncio]').textContent = `${mes.selectedOptions[0].textContent} de ${anio}`;
            raiz.querySelector('[data-mes-anterior]').disabled = anio === 1 && mesNumero === 1;
            raiz.querySelector('[data-mes-siguiente]').disabled = anio === 9999 && mesNumero === 12;
            if (conHora) {
                const diaElegido = control.leer()?.dia;
                const historico = control.leer()?.iso === control.original;
                const hoy = diaElegido === raiz.dataset.minCierre.slice(0, 10) && !historico;
                hora.min = hoy ? +raiz.dataset.minCierre.slice(11, 13) : 0;
                minuto.min = hoy && +hora.value === +hora.min ? +raiz.dataset.minCierre.slice(14, 16) : 0;
            }
        };
        abrir.hidden = false;
        calendario.querySelectorAll('input, select').forEach(campo => { campo.disabled = true; });
        abrir.addEventListener('click', () => {
            if (!calendario.hidden) { cerrar(); return; }
            controles.forEach(otro => { if (otro !== control) otro.cerrar?.(); });
            const actual = control.leer();
            cursor = actual?.fecha ?? desdeISO(raiz.dataset.hoy);
            focoDia = actual?.dia ?? raiz.dataset.hoy;
            if (conHora) { hora.value = +(actual?.hh ?? raiz.dataset.minCierre.slice(11, 13)); minuto.value = +(actual?.mm ?? raiz.dataset.minCierre.slice(14, 16)); }
            calendario.hidden = false;
            calendario.querySelectorAll('input, select').forEach(campo => { campo.disabled = false; });
            abrir.setAttribute('aria-expanded', 'true');
            dibujar();
            (dias.querySelector('button[tabindex="0"]') ?? mes).focus();
        });
        control.cerrar = () => cerrar(false);
        raiz.querySelector('[data-fecha-cerrar]').addEventListener('click', () => cerrar());
        calendario.addEventListener('keydown', evento => {
            if (evento.key === 'Escape') { evento.preventDefault(); cerrar(); }
            if (evento.key === 'Enter' && evento.target.tagName === 'INPUT') evento.preventDefault();
        });
        const moverMes = cantidad => { cursor = fechaUTC(cursor.getUTCFullYear(), cursor.getUTCMonth() + 1 + cantidad, 1); focoDia = ''; dibujar(); };
        raiz.querySelector('[data-mes-anterior]').addEventListener('click', () => moverMes(-1));
        raiz.querySelector('[data-mes-siguiente]').addEventListener('click', () => moverMes(1));
        const cambiarMes = () => {
            if (!ano.checkValidity() || !ano.value || +ano.value < 1 || +ano.value > 9999) return;
            cursor = fechaUTC(+ano.value, +mes.value, 1);
            focoDia = '';
            dibujar();
        };
        mes.addEventListener('change', cambiarMes);
        ano.addEventListener('change', cambiarMes);
        const cambiarHora = () => {
            const fecha = control.leer()?.dia ?? isoDia(cursor);
            if (hora.value === '' || minuto.value === '') return;
            elegir(`${fecha}T${pad(hora.value)}:${pad(minuto.value)}`);
        };
        hora?.addEventListener('input', cambiarHora);
        minuto?.addEventListener('input', cambiarHora);
        texto.addEventListener('input', () => { control.tocado = true; actualizar(); });
        texto.addEventListener('blur', () => { control.tocado = true; control.validar(true); });
        texto.addEventListener('invalid', () => control.validar(true));
        document.addEventListener('click', evento => { if (!calendario.hidden && !raiz.contains(evento.target)) cerrar(false); });
    });
    actualizar();
})();
