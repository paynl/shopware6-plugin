import Plugin from 'src/plugin-system/plugin.class';

export default class ExamplePlugin extends Plugin {
    static options = {
        /**
         * Specifies the text that is prompted to the user
         * @type string
         */
        text: 'just some ridiculous text.',
    };

    init() {
        document.getElementById('cocField').onchange = (select) => {
            const dropText = select.options[select.selectedIndex].text;
            const showField = document.getElementById('cocField');
            const nl = 'Netherlands';
            const be = 'Belgium';

            if (dropText === nl || dropText === be){
                showField.style.display = 'block';
            } else {
                showField.style.display = 'none';
            }
        }

        /*window.onclick = function idealBanks(displayBanks) {
                const getBanks = document.getElementById('banks')
                if (displayBanks === 'block') {
                    getBanks.style.display = displayBanks;
                } else {
                    getBanks.style.display = displayBanks;
                }
            }

            const divState = {};

            window.onclick = function showhide(id) {
                if (document.getElementById) {
                    const divid = document.getElementById(id);
                    divState[id] = (!divState[id]);
                    //close others
                    for (const div in divState) {
                        if (divState[div] && div !== id) {
                            document.getElementById(div).style.display = 'none';
                            divState[div] = false;
                        }
                    }
                    divid.style.display = (divid.style.display === 'block' ? 'none' : 'block');
                }
            }*/

    }
}
