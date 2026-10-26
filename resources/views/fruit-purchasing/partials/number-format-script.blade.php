<script>
    /**
     * Al salir de un campo, muestra el número como se lee en Colombia:
     * 18000 → «18.000», 4.7 → «4,7». El servidor entiende ambas formas.
     */
    window.frondaFormatNumber = function (value) {
        let text = String(value).replace(/[\s$ ]/g, '');
        if (text === '') return '';
        if (text.includes(',') && text.includes('.')) text = text.replace(/\./g, '').replace(',', '.');
        else if (text.includes(',')) text = text.replace(',', '.');
        else if (/^-?\d{1,3}(\.\d{3})+$/.test(text)) text = text.replace(/\./g, '');
        const number = Number(text);
        return Number.isFinite(number) ? number.toLocaleString('es-CO', { maximumFractionDigits: 2 }) : value;
    };
</script>
