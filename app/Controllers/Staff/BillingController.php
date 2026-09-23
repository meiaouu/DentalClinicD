<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\BillingPaymentRepository;
use App\Repositories\BillingRepository;
use PDO;
use RuntimeException;
use Throwable;

class BillingController
{
    private PDO $db;
    private BillingRepository $billings;
    private BillingPaymentRepository $payments;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->billings = new BillingRepository();
        $this->payments = new BillingPaymentRepository();
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $filters = [
            'keyword' => trim((string) ($_GET['keyword'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'per_page' => 15,
        ];

        $result = $this->billings->paginate($filters);

        View::render('staff/billing/index', [
            'summary' => $this->billings->summary(),
            'billings' => $result['rows'],
            'readyAppointments' => $this->billings->findCompletedAppointmentsWithoutBilling([
                'keyword' => $filters['keyword'],
            ]),
            'filters' => $filters,
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'total' => $result['total'],
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function show(): void
    {
        Auth::requireRole('staff');

        $billingId = (int) ($_GET['id'] ?? $_GET['billing_id'] ?? 0);

        if ($billingId <= 0) {
            http_response_code(404);
            exit('Billing record not found.');
        }

        $billing = $this->billings->findById($billingId);

        if (!$billing) {
            http_response_code(404);
            exit('Billing record not found.');
        }

        View::render('staff/billing/show', [
            'billing' => $billing,
            'items' => $this->billings->findItems($billingId),
            'payments' => $this->payments->findByBillingId($billingId),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function generateFromAppointment(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $staffUserId = (int) (Auth::user()['user_id'] ?? 0);

        try {
            if ($appointmentId <= 0) {
                throw new RuntimeException('Invalid appointment.');
            }

            if ($staffUserId <= 0) {
                throw new RuntimeException('Invalid staff user.');
            }

            $billingId = $this->billings->createFromAppointment($appointmentId, $staffUserId);

            Session::set('flash_success', 'Billing record generated successfully.');
            header('Location: ' . $this->url('/staff/billing/show?id=' . $billingId));
            exit;
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
            header('Location: ' . $this->url('/staff/billing'));
            exit;
        }
    }

    public function addPayment(): void
{
    Auth::requireRole('staff');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $billingId = (int) ($_POST['billing_id'] ?? 0);
    $staffUserId = (int) (Auth::user()['user_id'] ?? 0);

    if ($billingId <= 0) {
        Session::set('flash_error', 'Invalid billing record.');
        header('Location: ' . $this->url('/staff/billing'));
        exit;
    }

    try {
        if ($staffUserId <= 0) {
            throw new RuntimeException('Invalid staff user.');
        }

        $amountPaid = $this->moneyValue($_POST['amount_paid'] ?? '');
        $paymentMethod = $this->validPaymentMethod($_POST['payment_method'] ?? '');
        $referenceNumber = $this->nullableText($_POST['reference_number'] ?? '', 120);
        $remarks = $this->nullableText($_POST['remarks'] ?? '', 1000);

        if ($amountPaid <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $this->db->beginTransaction();

        $billing = $this->findBillingForPaymentUpdate($billingId);

        if (!$billing) {
            throw new RuntimeException('Billing record not found.');
        }

        $currentStatus = strtolower(trim((string) ($billing['payment_status'] ?? 'unpaid')));

        if ($currentStatus === 'cancelled') {
            throw new RuntimeException('Cancelled billing records cannot receive payments.');
        }

        if ($currentStatus === 'paid') {
            throw new RuntimeException('This billing record is already fully paid.');
        }

        $balanceBefore = round((float) ($billing['balance'] ?? 0), 2);

        if ($balanceBefore <= 0) {
            throw new RuntimeException('This billing record has no remaining balance.');
        }

        if ($amountPaid > $balanceBefore) {
            throw new RuntimeException('Payment amount cannot be greater than the current balance.');
        }

        $balanceAfter = max(0, round($balanceBefore - $amountPaid, 2));
        $newAmountPaid = round((float) ($billing['amount_paid'] ?? 0) + $amountPaid, 2);
        $newStatus = $this->automaticPaymentStatus($newAmountPaid, $balanceAfter);

        $receiptNumber = $this->payments->generateReceiptNumber();

        $paymentId = $this->payments->createPayment([
            'billing_id' => $billingId,
            'received_by' => $staffUserId,
            'receipt_number' => $receiptNumber,
            'amount_paid' => $amountPaid,
            'payment_method' => $paymentMethod,
            'reference_number' => $referenceNumber,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'remarks' => $remarks,
        ]);

        $this->billings->updatePaymentSummary(
            $billingId,
            $newAmountPaid,
            $balanceAfter,
            $newStatus
        );

        $this->db->commit();

        Session::set('flash_success', 'Payment recorded successfully.');
        header('Location: ' . $this->url('/staff/billing/receipt?id=' . $paymentId));
        exit;
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Session::set('flash_error', $e->getMessage());
        header('Location: ' . $this->url('/staff/billing/show?id=' . $billingId));
        exit;
    }
}

    public function receipt(): void
    {
        Auth::requireRole('staff');

        $paymentId = (int) ($_GET['id'] ?? $_GET['payment_id'] ?? 0);

        if ($paymentId <= 0) {
            http_response_code(404);
            exit('Receipt not found.');
        }

        $receipt = $this->payments->findPaymentReceipt($paymentId);

        if (!$receipt) {
            http_response_code(404);
            exit('Receipt not found.');
        }

        View::render('staff/billing/receipt', [
            'receipt' => $receipt,
        ]);
    }

    private function findBillingForPaymentUpdate(int $billingId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billings
            WHERE billing_id = :billing_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
        ]);

        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

        return $billing ?: null;
    }

    private function automaticPaymentStatus(float $amountPaid, float $balance): string
    {
        if ($balance <= 0) {
            return 'paid';
        }

        if ($amountPaid > 0 && $balance > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    private function validPaymentMethod($value): string
{
    $value = strtolower(trim((string) $value));

    if ($value === '') {
        $value = 'cash';
    }

    if ($value !== 'cash') {
        throw new RuntimeException('Only cash payment is currently allowed.');
    }

    return 'cash';
}

    private function moneyValue($value): float
    {
        $value = trim(str_replace(',', '', (string) $value));

        if ($value === '') {
            throw new RuntimeException('Payment amount is required.');
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new RuntimeException('Invalid payment amount format.');
        }

        $amount = round((float) $value, 2);

        if ($amount < 0) {
            throw new RuntimeException('Payment amount cannot be negative.');
        }

        return $amount;
    }

    private function nullableText($value, int $maxLength): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}