export default class VersionComparator {
    isEqual(a, b) {
        return this._compareVersions(a, b, '==');
    }

    isNotEqual(a, b) {
        return this._compareVersions(a, b, '!=');
    }

    isGreaterThan(a, b) {
        return this._compareVersions(a, b, '>');
    }

    isGreaterOrEqual(a, b) {
        return this._compareVersions(a, b, '>=');
    }

    isLessThan(a, b) {
        return this._compareVersions(a, b, '<');
    }

    isLessOrEqual(a, b) {
        return this._compareVersions(a, b, '<=');
    }

    _compareVersions(a, b, operator = '==') {
        const va = this._parseVersion(a);
        const vb = this._parseVersion(b);

        if (!va || !vb) return false;

        const diff = ['major', 'minor', 'patch', 'build'].map(k => va[k] - vb[k]);

        switch (operator) {
            case '==':
                return diff.every(val => val === 0);
            case '!=':
                return diff.some(val => val !== 0);
            case '>':
                return this._compareParts(diff) > 0;
            case '>=':
                return this._compareParts(diff) >= 0;
            case '<':
                return this._compareParts(diff) < 0;
            case '<=':
                return this._compareParts(diff) <= 0;
            default:
                return false;
        }
    }

    _compareParts(differences) {
        for (let val of differences) {
            if (val !== 0) return val;
        }
        return 0;
    }

    _parseVersion(versionStr) {
        const regex = /(?<major>\d+)\.?(?<minor>\d+)?\.?(?<patch>\d+)?\.?(?<build>\d+)?-?(?<label>[a-z]+)?\.?(?<labelVer>\d+(?:\.\d+)*)?/i;
        const match = versionStr.match(regex);

        if (!match || !match.groups) {
            console.warn(`Invalid version format: ${versionStr}`);
            return null;
        }

        const { major, minor, patch, build, label, labelVer } = match.groups;

        return {
            major: parseInt(major) || 0,
            minor: parseInt(minor) || 0,
            patch: parseInt(patch) || 0,
            build: parseInt(build) || 0,
            label,
            labelVer
        };
    }

    formatVersion(versionStr) {
        const parsed = this._parseVersion(versionStr);
        if (!parsed) return versionStr;

        let labelText = this._labelText(parsed.label);
        let version = `v${parsed.major}.${parsed.minor}.${parsed.patch}.${parsed.build}`;

        if (parsed.label) {
            version += ` ${labelText}`;
        } else {
            version += ' Stable';
        }

        if (parsed.labelVer) {
            version += ` ${parsed.labelVer}`;
        }

        return version;
    }

    _labelText(label) {
        const map = {
            dp: 'Developer Preview',
            rc: 'Release Candidate',
            dev: 'Developer Build',
            ea: 'Early Access'
        };
        return map[label] || label || '';
    }
}