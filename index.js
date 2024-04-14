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


let EMPTY = 0;
let SHIP = 1;
let SHOT = 2;
let SHIPSHOT = 3;

if(route == 'displ') {
    document.documentElement.style.setProperty('--columns', `repeat(${parseInt(params.get('x-width') ?? '10')+1}, 1fr)`);
    document.documentElement.style.setProperty('--rows', `repeat(${parseInt(params.get('y-width') ?? '10')+1}, 1fr)`); 

    let dims = atob(TEMPLATE_STR).split(';')[0].replace('TEMPLATE', '').split(',');
    
    let z_width = parseInt(dims[0]);
    let y_width = parseInt(dims[1]);
    let x_width = parseInt(dims[2]);
    
    let fields = [...document.querySelectorAll('.field')];

    function fields_index(z, y, x) {
        return fields[z * x_width * y_width + y * x_width + x];
    }

    let layers = [...document.querySelectorAll('.layer')];

    document.body.addEventListener('keypress', (e) => {
        switch (e.key) {
            case 'z':
                if (CUR_LAYER < z_width) CUR_LAYER += 1;
                else return;
                break;
            case 'x':
                if (CUR_LAYER > 1) CUR_LAYER -= 1;
                else return;
                break;
        }

        layers.forEach((layer, z) => {
            let opacity = Math.abs(CUR_LAYER - (z+1)) <= Z_DISPL ? (1-Math.abs(z+1-CUR_LAYER) / z_width).toFixed(2) : 0;
            let tx = (CUR_LAYER - (z+1)) * 2.5;
            let ty = - tx;
            let coord_visibility = (tx == 0 ? 'visible' : 'hidden');
            let outline = (tx == 0 ? '' : "outline: none;");
            let events = (tx == 0 ? '' : 'no-events');

            layer.classList.remove('events');
            if(events === 'no-events') { // check required or else DOMexception
                layer.classList.add(events);
            }
            layer.setAttribute('style', `opacity: ${opacity}; transform: translate(${tx}%, ${ty}%);`);
            layer.querySelectorAll('.label').forEach(label => {
                label.setAttribute('style', `visibility: ${coord_visibility};`)
            })
            layer.querySelectorAll('.field').forEach(field => {
                field.setAttribute('style', outline);
            })
            layer.querySelector('.pad').setAttribute('style', outline);
        })
    });
}


if(route == 'play') {
    document.documentElement.style.setProperty('--columns', `repeat(${parseInt(params.get('x-width') ?? '10')+1}, 1fr)`);
    document.documentElement.style.setProperty('--rows', `repeat(${parseInt(params.get('y-width') ?? '10')+1}, 1fr)`);
    
    let dims = atob(TEMPLATE_STR).split(';')[0].replace('TEMPLATE', '').split(',');
    
    let z_width = parseInt(dims[0]);
    let y_width = parseInt(dims[1]);
    let x_width = parseInt(dims[2]);
    
    let fields = [...document.querySelectorAll('.field')];

    function fields_index(z, y, x) {
        return fields[z * x_width * y_width + y * x_width + x];
    }

    let layers = [...document.querySelectorAll('.layer')];

    document.body.addEventListener('keypress', (e) => {
        switch (e.key) {
            case 'z':
                if (CUR_LAYER < z_width) CUR_LAYER += 1;
                else return;
                break;
            case 'x':
                if (CUR_LAYER > 1) CUR_LAYER -= 1;
                else return;
                break;
        }

        layers.forEach((layer, z) => {
            let opacity = Math.abs(CUR_LAYER - (z+1)) <= Z_DISPL ? (1-Math.abs(z+1-CUR_LAYER) / z_width).toFixed(2) : 0;
            let tx = (CUR_LAYER - (z+1)) * 2.5;
            let ty = - tx;
            let coord_visibility = (tx == 0 ? 'visible' : 'hidden');
            let outline = (tx == 0 ? '' : "outline: none;");
            let events = (tx == 0 ? '' : 'no-events');

            layer.classList.remove('events');
            if(events === 'no-events') { // check required or else DOMexception
                layer.classList.add(events);
            }
            layer.setAttribute('style', `opacity: ${opacity}; transform: translate(${tx}%, ${ty}%);`);
            layer.querySelectorAll('.label').forEach(label => {
                label.setAttribute('style', `visibility: ${coord_visibility};`)
            })
            layer.querySelectorAll('.field').forEach(field => {
                field.setAttribute('style', outline);
            })
            layer.querySelector('.pad').setAttribute('style', outline);
        })
    });

    let score = 0;

    let total = 0;
    for (let z_idx = 0; z_idx < z_width; z_idx++) {
        for (let y_idx = 0; y_idx < y_width; y_idx++) {
            for (let x_idx = 0; x_idx < x_width; x_idx++) {
                fields[total].addEventListener('click', (el) => {
                    let x = parseInt(el.target.getAttribute('x'));
                    let y = parseInt(el.target.getAttribute('y'));
                    let z = parseInt(el.target.getAttribute('z'));
                    switch(GAME_JSON[z][y][x]) {
                        case EMPTY:
                            el.target.classList.replace("empty", "shot");
                            score += 1;
                            break;
                        case SHIP:
                            el.target.classList.replace("empty", "shipshot");
                            score += 1;
                            break;
                    }

                    let search = bfs(z, y, x);
                    if(!search[0]) {
                        let to_explode = new Set();
                        if(search[1].length) search[1].forEach(el => {
                            let [cur_z, cur_y, cur_x] = el;
                            if(cur_z > 0) {
                                if(cur_y > 0) {
                                    if(cur_x > 0) {
                                        to_explode.add(fields_index(cur_z-1, cur_y-1, cur_x-1));
                                    }
                                    to_explode.add(fields_index(cur_z-1, cur_y-1, cur_x));
                                    if(cur_x < x_width - 1) {
                                        to_explode.add(fields_index(cur_z-1, cur_y-1, cur_x+1));
                                    }
                                }
                                if(cur_x > 0) {
                                    to_explode.add(fields_index(cur_z-1, cur_y, cur_x-1));
                                }
                                to_explode.add(fields_index(cur_z-1, cur_y, cur_x));
                                if(cur_x < x_width - 1) {
                                    to_explode.add(fields_index(cur_z-1, cur_y, cur_x+1));
                                }
        
                                if(cur_y < y_width - 1) {
                                    if(cur_x > 0) {
                                        to_explode.add(fields_index(cur_z-1, cur_y+1, cur_x-1));
                                    }
                                    to_explode.add(fields_index(cur_z-1, cur_y+1, cur_x));
                                    if(cur_x < x_width - 1) {
                                        to_explode.add(fields_index(cur_z-1, cur_y+1, cur_x+1));
                                    }
                                }
                            }
                            if(cur_y > 0) {
                                if(cur_x > 0) {
                                    to_explode.add(fields_index(cur_z, cur_y-1, cur_x-1));
                                }
                                to_explode.add(fields_index(cur_z, cur_y-1, cur_x));
                                if(cur_x < x_width - 1) {
                                    to_explode.add(fields_index(cur_z, cur_y-1, cur_x+1));
                                }
                            }
                            if(cur_x > 0) {
                                to_explode.add(fields_index(cur_z, cur_y, cur_x-1));
                            }
                            if(cur_x < x_width - 1) {
                                to_explode.add(fields_index(cur_z, cur_y, cur_x+1));
                            }

                            if(cur_y < y_width - 1) {
                                if(cur_x > 0) {
                                    to_explode.add(fields_index(cur_z, cur_y+1, cur_x-1));
                                }
                                to_explode.add(fields_index(cur_z, cur_y+1, cur_x));
                                if(cur_x < x_width - 1) {
                                    to_explode.add(fields_index(cur_z, cur_y+1, cur_x+1));
                                }
                            }
                            if(cur_z < z_width - 1) {
                                if(cur_y > 0) {
                                    if(cur_x > 0) {
                                        to_explode.add(fields_index(cur_z+1, cur_y-1, cur_x-1));
                                    }
                                    to_explode.add(fields_index(cur_z+1, cur_y-1, cur_x));
                                    if(cur_x < x_width - 1) {
                                        to_explode.add(fields_index(cur_z+1, cur_y-1, cur_x+1));
                                    }
                                }
                                if(cur_x > 0) {
                                    to_explode.add(fields_index(cur_z+1, cur_y, cur_x-1));
                                }
                                to_explode.add(fields_index(cur_z+1, cur_y, cur_x));
                                if(cur_x < x_width - 1) {
                                    to_explode.add(fields_index(cur_z+1, cur_y, cur_x+1));
                                }
        
                                if(cur_y < y_width - 1) {
                                    if(cur_x > 0) {
                                        to_explode.add(fields_index(cur_z+1, cur_y+1, cur_x-1));
                                    }
                                    to_explode.add(fields_index(cur_z+1, cur_y+1, cur_x));
                                    if(cur_x < x_width - 1) {
                                        to_explode.add(fields_index(cur_z+1, cur_y+1, cur_x+1));
                                    }
                                }
                            }
                        });

                        to_explode.forEach((f) => {
                            if(GAME_JSON[parseInt(f.getAttribute('z'))][parseInt(f.getAttribute('y'))][parseInt(f.getAttribute('x'))] == EMPTY) {
                                f.classList.replace('empty', 'shot');
                            }
                        })

                        if (check_for_win()) {
                            alert(`Wygrałeś! Wynik: ${Math.max(score*(z_width > 1 ? 5 : 1) - x_width * y_width * z_width, 0)} (max(kliknięcia - ilość pól, 0))\nSpoglądaj teraz na swoje zwycięstwo, lub utwórz nową grę aby spróbować ponownie...`)
                        }
                    }
                }, { once: true });
                total += 1;
            }
        }
    }

    function check_for_win() {
        for (let z_idx = 0; z_idx < z_width; z_idx++) {
            for (let y_idx = 0; y_idx < y_width; y_idx++) {
                for (let x_idx = 0; x_idx < x_width; x_idx++) {
                    if(GAME_JSON[z_idx][y_idx][x_idx] == SHIP) {
                        if(fields_index(z_idx, y_idx, x_idx).classList.contains('ship') || fields_index(z_idx, y_idx, x_idx).classList.contains('empty')) {
                            return false;
                        }
                    }
                }
            }
        }
        unset_events();
        return true;
    }

    function unset_events() {
        for (let z_idx = 0; z_idx < z_width; z_idx++) {
            for (let y_idx = 0; y_idx < y_width; y_idx++) {
                for (let x_idx = 0; x_idx < x_width; x_idx++) {
                    // removes all event listeners, currently no better alternative in html5
                    let orig = fields_index(z_idx, y_idx, x_idx);
                    let copy = orig.cloneNode(true);
                    orig.parentNode.replaceChild(copy, orig);
                }
            }
        }
    }

    function eq(arr1, arr2) {
        return arr1.every((val, idx) => {
            return val === arr2[idx]
        });
    }

    function bfs(z, y, x) {
        const queue = [[z, y, x]];
        const visited = [];
        let result1 = false;
        let result2 = [];

        while (queue.length) {
            const cur = queue.shift();
            const [cur_z, cur_y, cur_x] = cur;

            if (!visited.some((val) => {
                return eq(val, [cur_z, cur_y, cur_x]);
            })) {
                visited.push(cur);
                
                if(GAME_JSON[cur_z][cur_y][cur_x] == SHIP) {
                    if(fields_index(cur_z, cur_y, cur_x).classList.contains('empty')) {
                        result1 = true;
                        // result2.push(cur);
                        return [true, []];
                    } else if(fields_index(cur_z, cur_y, cur_x).classList.contains('shipshot')) {
                        result2.push(cur);
                    }

                    if(cur_z > 0) {
                        if(GAME_JSON[cur_z-1][cur_y][cur_x] == SHIP) queue.push([cur_z-1, cur_y, cur_x]);
                    }
                    if(cur_y > 0) {
                        if(GAME_JSON[cur_z][cur_y-1][cur_x] == SHIP) queue.push([cur_z, cur_y-1, cur_x]);
                    }
                    if(cur_y < y_width - 1) {
                        if(GAME_JSON[cur_z][cur_y+1][cur_x] == SHIP) queue.push([cur_z, cur_y+1, cur_x]);
                    }
                    if(cur_x > 0) {
                        if(GAME_JSON[cur_z][cur_y][cur_x-1] == SHIP) queue.push([cur_z, cur_y, cur_x-1]);
                    }
                    if(cur_x < x_width - 1) {
                        if(GAME_JSON[cur_z][cur_y][cur_x+1] == SHIP) queue.push([cur_z, cur_y, cur_x+1]);
                    }if(cur_z < z_width - 1) {
                        if(GAME_JSON[cur_z+1][cur_y][cur_x] == SHIP) queue.push([cur_z+1, cur_y, cur_x]);
                    }
                }
            }
        }

        return [result1, result2];
    }
}

function saveState() {
    let dims = atob(TEMPLATE_STR).split(';')[0].replace('TEMPLATE', '');

    // console.log(dims + ';' + JSON.stringify(GAME_JSON));
    window.location.href = window.location.toString().split('/').slice(0, -1).join('/') + '?route=save&payload=' + btoa(dims + ';' + JSON.stringify(GAME_JSON)).replace('=', '-');
}

function saveTemplate() {
    window.location.href = window.location.toString().split('/').slice(0, -1).join('/') + '?route=save&payload=' + TEMPLATE_STR.replace('=', '-');
}

function loadfile() {
    let file = document.getElementById('select-savefile').value;
    let next = document.getElementById('ui-type').value;
    window.location.href = window.location.toString().split('/').slice(0, -1).join('/') + '?route=' + next + '&file=' + file;
}