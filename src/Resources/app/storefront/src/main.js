function showCoc (sel) {
    let dropText = sel.options[sel.selectedIndex].text;
    let showField = document.getElementById('cocField');
    let nl = 'Netherlands';
    let be = 'Belgium';

    if (dropText === nl || dropText === be){
        showField.style.display = 'block';
    } else {
        showField.style.display = 'none';
    }
}

function idealBanks(displayBanks) {
    let getBanks = document.getElementById('banks')
    if (displayBanks === 'block') {
        getBanks.style.display = displayBanks;
    } else {
        getBanks.style.display = displayBanks;
    }
}

let divState = {};

function showhide(id) {
    if (document.getElementById) {
        let divid = document.getElementById(id);
        divState[id] = (!divState[id]);
        //close others
        for (let div in divState) {
            if (divState[div] && div !== id) {
                document.getElementById(div).style.display = 'none';
                divState[div] = false;
            }
        }
        divid.style.display = (divid.style.display === 'block' ? 'none' : 'block');
    }
}