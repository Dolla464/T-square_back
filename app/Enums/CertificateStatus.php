<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case Issued  = 'issued';
    case Pending = 'pending';
    case Revoked = 'revoked';

    /** Human-readable label for display. */
    public function label(): string
    {
        return match ($this) {
            self::Issued  => 'Issued',
            self::Pending => 'Pending',
            self::Revoked => 'Revoked',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
