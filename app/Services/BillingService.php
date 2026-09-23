<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\ReceiptRepository;
use App\Repositories\TreatmentPaymentRepository;
use App\Repositories\TreatmentRepository;
use RuntimeException;

class BillingService
{
    private TreatmentRepository $treatments;
    private TreatmentPaymentRepository $payments;
    private ReceiptRepository $receipts;

    public function __construct()
    {
        $this->treatments = new TreatmentRepository();
        $this->payments = new TreatmentPaymentRepository();
        $this->receipts = new ReceiptRepository();
    }

    public function getBillingSnapshot(int $treatmentId): array
    {
        $treatment = $this->treatments->findDetailedById($treatmentId);

        if (!$treatment) {
            throw new RuntimeException('Treatment not found.');
        }

        $payments = $this->payments->getByTreatmentId($treatmentId);
        $receipts = $this->receipts->getByTreatmentId($treatmentId);
        $totalPaid = $this->payments->sumByTreatmentId($treatmentId);

        $actualCharge = (float) ($treatment['actual_charge'] ?? 0);
        $balance = $actualCharge - $totalPaid;
        if ($balance < 0) {
            $balance = 0;
        }

        return [
            'treatment' => $treatment,
            'payments' => $payments,
            'receipts' => $receipts,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'payment_status' => $this->resolvePaymentStatus($actualCharge, $totalPaid),
        ];
    }

    public function recordPayment(
        int $treatmentId,
        float $amountPaid,
        string $paymentMethod,
        ?string $referenceNumber,
        int $receivedBy,
        string $paymentDate,
        ?string $remarks,
        bool $issueReceipt = true
    ): array {
        $treatment = $this->treatments->findDetailedById($treatmentId);

        if (!$treatment) {
            throw new RuntimeException('Treatment not found.');
        }

        if ($amountPaid <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $actualCharge = (float) ($treatment['actual_charge'] ?? 0);

        if ($actualCharge <= 0) {
            throw new RuntimeException('Actual charge must be set before recording payment.');
        }

        $currentPaid = $this->payments->sumByTreatmentId($treatmentId);
        $newTotalPaid = $currentPaid + $amountPaid;

        if ($newTotalPaid > $actualCharge) {
            throw new RuntimeException('Payment exceeds the remaining balance.');
        }

        $newBalance = $actualCharge - $newTotalPaid;
        if ($newBalance < 0) {
            $newBalance = 0;
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $paymentId = $this->payments->create([
                'treatment_id' => $treatmentId,
                'appointment_id' => $treatment['appointment_id'],
                'patient_id' => $treatment['patient_id'],
                'amount_paid' => $amountPaid,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'received_by' => $receivedBy,
                'payment_date' => $paymentDate,
                'remarks' => $remarks,
            ]);

            $this->treatments->updateFinancialSummary(
                $treatmentId,
                $newTotalPaid,
                $newBalance
            );

            $receipt = null;

            if ($issueReceipt) {
                $receiptNumber = $this->generateReceiptNumber();
                $this->receipts->create([
                    'treatment_id' => $treatmentId,
                    'payment_id' => $paymentId,
                    'receipt_number' => $receiptNumber,
                    'issued_by' => $receivedBy,
                    'issued_at' => date('Y-m-d H:i:s'),
                ]);

                $receipt = $this->receipts->findByPaymentId($paymentId);
            }

            $db->commit();

            return [
                'payment_id' => $paymentId,
                'receipt' => $receipt,
                'new_total_paid' => $newTotalPaid,
                'new_balance' => $newBalance,
                'payment_status' => $this->resolvePaymentStatus($actualCharge, $newTotalPaid),
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function resolvePaymentStatus(float $actualCharge, float $totalPaid): string
    {
        if ($totalPaid <= 0) {
            return 'unpaid';
        }

        if ($totalPaid >= $actualCharge) {
            return 'paid';
        }

        return 'partially_paid';
    }

    private function generateReceiptNumber(): string
    {
        return 'RCT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}