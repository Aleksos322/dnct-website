(() => {
    const data = document.getElementById('chooser-data');
    if (!data) return;
    const catalog = JSON.parse(data.textContent);
    const players = document.getElementById('players');
    const playersValue = document.getElementById('players-value');
    const styleFieldset = document.getElementById('style-fieldset');
    const title = document.getElementById('result-title');
    const specs = document.getElementById('result-specs');
    const reason = document.getElementById('result-reason');
    const link = document.getElementById('result-link');

    const game = () => document.querySelector('input[name="game"]:checked').value;
    const style = () => document.querySelector('input[name="style"]:checked').value;
    const plural = n => n === 1 ? 'osoba' : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14) ? 'osoby' : 'osób');
    const money = value => new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value);

    function render() {
        const type = game(); const count = Number(players.value); const current = catalog[type];
        playersValue.textContent = count + ' ' + plural(count);
        const progress = ((count - Number(players.min)) / (Number(players.max) - Number(players.min))) * 100;
        players.style.setProperty('--range-progress', progress + '%');
        styleFieldset.hidden = type !== 'minecraft';
        let item; let message;
        if (type === 'minecraft') {
            const s = style();
            const needed = s === 'mods' ? (count <= 5 ? 8 : count <= 10 ? 10 : count <= 20 ? 12 : 16)
                : s === 'plugins' ? (count <= 5 ? 4 : count <= 10 ? 6 : count <= 20 ? 8 : 10)
                : (count <= 5 ? 3 : count <= 10 ? 4 : 6);
            // Plany z bazy są w MB, a rekomendacja jest w GB.
            item = current.plans.filter(p => p.available).find(p => p.ram >= needed * 1024);
            message = `Dla ${count} ${plural(count)} i trybu ${s === 'mods' ? 'mody / modpack' : s === 'plugins' ? 'pluginy' : 'vanilla'} celujemy w co najmniej ${needed} GB RAM.`;
            if (item) specs.textContent = `${item.ram >= 1024 ? item.ram / 1024 + ' GB' : item.ram + ' MB'} RAM · ${item.cpu} vCore · ${item.disk >= 1024 ? item.disk / 1024 + ' GB' : item.disk + ' MB'} SSD · od ${money(item.price)} / mies.`;
        } else {
            item = current.tiers.filter(t => t.available).find(t => t.slots >= count);
            message = `Wybraliśmy pierwszy dostępny próg, który mieści ${count} ${plural(count)} jednocześnie.`;
            if (item) specs.textContent = `${item.slots} slotów · ${item.ram} GB RAM · ${item.cpu} vCore · ${item.disk >= 1024 ? item.disk / 1024 + ' GB' : item.disk + ' MB'} SSD · od ${money(item.price)} / mies.`;
        }
        if (!item) { title.textContent = 'Potrzebujesz większego wariantu'; specs.textContent = 'Nie ma obecnie dostępnego pakietu o takich parametrach.'; reason.textContent = 'Napisz do nas — sprawdzimy możliwość przygotowania serwera.'; link.textContent = 'Napisz na Discordzie'; link.href = 'https://discord.gg/4kcXP5WmQ8'; return; }
        title.textContent = item.name || `${item.slots} slotów`;
        reason.textContent = message; link.textContent = `Zobacz pakiety ${current.name}`; link.href = '/cennik#plans';
    }
    document.querySelectorAll('#server-chooser input').forEach(el => el.addEventListener('input', render));
    render();
})();
