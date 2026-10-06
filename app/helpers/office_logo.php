<?php

function officeLogoPath(?string $officeName): string
{
    $name = strtolower((string) $officeName);
    $normalized = preg_replace('/[^a-z0-9]+/', '', $name);

    if (str_contains($normalized, 'collegeofbusinesseducation') || $normalized === 'cbe') {
        return 'assets/images/offices_logo/CBE.png';
    }
    if (str_contains($normalized, 'collegeofteachereducation') || $normalized === 'cte') {
        return 'assets/images/offices_logo/CTE.png';
    }
    if (
        str_contains($normalized, 'collegeofjusticeeducation')
        || $normalized === 'ccje'
        || $normalized === 'cje'
    ) {
        return 'assets/images/offices_logo/CCJE.png';
    }
    if (str_contains($normalized, 'guidance')) {
        return 'assets/images/offices_logo/guidance-removebg-preview.png';
    }
    if (str_contains($normalized, 'finance') || str_contains($normalized, 'cashier')) {
        return 'assets/images/offices_logo/finance-removebg-preview.png';
    }
    if (
        str_contains($normalized, 'psychology')
        || str_contains($normalized, 'psychologydepartment')
        || str_contains($normalized, 'psychologydept')
    ) {
        return 'assets/images/offices_logo/PYSCHOLOGY.png';
    }
    if (
        str_contains($normalized, 'computerscience')
        || str_contains($normalized, 'collegesofcomputingstudies')
        || str_contains($normalized, 'collegeofcomputingstudies')
        || str_contains($normalized, 'collegeofcomputerstudies')
        || $normalized === 'ccs'
    ) {
        return 'assets/images/offices_logo/CCS.png';
    }
    if (str_contains($normalized, 'itcd')) {
        return 'assets/images/offices_logo/ITCD.png';
    }

    return 'assets/images/offices_logo/CRMC LOGO.png';
}
