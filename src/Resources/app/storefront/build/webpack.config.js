const { join, resolve } = require('path');

module.exports = () => {
    return {
        resolve: {
            alias: {
                '@datepicker': resolve(
                    join(__dirname, '..', 'node_modules', 'vanillajs-datepicker')
                )
            }
        }
    };
};
