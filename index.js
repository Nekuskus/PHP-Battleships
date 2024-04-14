let params = new URLSearchParams(document.location.search);
let route = params.get("route");
let ships = [];

if (route == null || route == 'set') {
    document.getElementById('save-template').classList.add('disabled');
    document.getElementById('save-state').classList.add('disabled');

    let form = document.getElementById('settings');
    let statki = document.getElementById('statki');
    form['add-ship'].addEventListener('click', () => {
        ships.push(`${form.length.value}->${form.count.value}`);
        statki.innerText = ships.join(', ');
    });

    form.addEventListener('submit', (e) => {
        let formdata = new FormData(form);
        if (form['ui-type'].value == 'play') {
            formdata.set('route', 'play');
        } else {
            formdata.set('route', 'displ');
        }
        formdata.delete('ui-type');
        formdata.delete('count');
        formdata.delete('length');
        formdata.set('ships', ships.join(','));
        window.location.href = window.location.toString().split('/').slice(0, -1).join('/') + '?' + new URLSearchParams(formdata).toString();
        e.preventDefault();
    })
}

if(route == 'displ') {
    document.documentElement.style.setProperty('--columns', `repeat(${parseInt(params.get('x-width') ?? '10')+1}, 1fr)`);
    document.documentElement.style.setProperty('--rows', `repeat(${parseInt(params.get('y-width') ?? '10')+1}, 1fr)`);
}

if(route == 'play') {
    document.documentElement.style.setProperty('--columns', `repeat(${parseInt(params.get('x-width') ?? '10')+1}, 1fr)`);
    document.documentElement.style.setProperty('--rows', `repeat(${parseInt(params.get('y-width') ?? '10')+1}, 1fr)`);
}

function saveTemplate() {
    window.location.href = window.location.toString().split('/').slice(0, -1).join('/') + '?route=save&payload=' + TEMPLATE_STR.replace('=', '-');
}

function saveState() {
    
    // window.location.href = window.location.toString().split('/').slice(0, -1).join('/') + '?route=save&payload=' + TEMPLATE_STR.replace('=', '-');
}

function loadfile() {
    let file = document.getElementById('select-savefile').value;
    let next = document.getElementById('ui-type').value;
    window.location.href = window.location.toString().split('/').slice(0, -1).join('/') + '?route=' + next + '&file=' + file;
}