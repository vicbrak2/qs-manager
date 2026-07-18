<?php

declare(strict_types=1);

namespace QSManager\Domain\Finance;

enum AccountingBasis: string
{
    case CASH_ESTIMATED = 'cash_estimated';
    case ACCRUAL = 'accrual';
}
