<?php

namespace App\Services;

use App\Models\TradeSignal;
use Illuminate\Support\Facades\Log;

class RiskManagementService
{
    // Maximum portfolio risk allowed per trade (1.00%)
    private const MAX_RISK_PERCENTAGE = 1.00;

    // Minimum required distance between entry and stop-loss as a fraction (0.1%)
    private const MIN_STOP_DISTANCE_PCT = 0.001;

    /**
     * Evaluate a pending TradeSignal against hard-coded risk rules.
     * Returns true if the signal passes all checks, false if rejected.
     * On rejection, persists the status and reason to the database.
     */
    public function evaluateSignal(TradeSignal $signal): bool
    {
        if ($rejection = $this->checkMaxRisk($signal)) {
            $this->reject($signal, $rejection);
            return false;
        }

        if ($rejection = $this->checkStopDistance($signal)) {
            $this->reject($signal, $rejection);
            return false;
        }

        return true;
    }

    // Rule 1: Risk percentage must not exceed MAX_RISK_PERCENTAGE.
    private function checkMaxRisk(TradeSignal $signal): ?string
    {
        if ((float) $signal->risk_percentage > self::MAX_RISK_PERCENTAGE) {
            return sprintf(
                'Risk percentage of %.2f%% exceeds the maximum allowed of %.2f%%.',
                $signal->risk_percentage,
                self::MAX_RISK_PERCENTAGE,
            );
        }

        return null;
    }

    // Rule 2: Distance between entry_price and stop_loss must exceed MIN_STOP_DISTANCE_PCT.
    private function checkStopDistance(TradeSignal $signal): ?string
    {
        $entry = (float) $signal->entry_price;
        $stop  = (float) $signal->stop_loss;

        if ($entry <= 0) {
            return 'entry_price must be greater than zero.';
        }

        $distance = abs($entry - $stop) / $entry;

        if ($distance <= self::MIN_STOP_DISTANCE_PCT) {
            return sprintf(
                'Stop-loss distance of %.4f%% is too tight; minimum required is %.1f%%.',
                $distance * 100,
                self::MIN_STOP_DISTANCE_PCT * 100,
            );
        }

        return null;
    }

    private function reject(TradeSignal $signal, string $reason): void
    {
        $signal->update([
            'status'           => 'REJECTED_BY_RISK',
            'rejection_reason' => $reason,
        ]);

        Log::warning('TradeSignal rejected by risk engine', [
            'uuid'             => $signal->getKey(),
            'agent_id'         => $signal->agent_id,
            'symbol'           => $signal->symbol,
            'rejection_reason' => $reason,
        ]);
    }
}
