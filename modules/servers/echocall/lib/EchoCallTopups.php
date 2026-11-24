<?php
/**
 * Credit top ups through WHMCS invoices.
 *
 * The client picks an amount, WHMCS issues a normal invoice (taxes, gateways
 * and reminders stay with WHMCS), and once that invoice is paid the net amount
 * is booked onto the EchoCall balance of the customer.
 *
 * Booking happens at most once per invoice. A row is claimed by switching it
 * from "pending" to "booking" in one statement, so two paid hooks or a hook
 * and the daily cron can never book the same invoice twice. A row stuck in
 * "booking" (the process died after asking EchoCall) is settled by looking for
 * its booking text in the customer's transactions before anything is retried.
 *
 * @package EchoCall
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;

final class EchoCallTopups
{
    public const TABLE = 'mod_echocall_topups';

    private const MAX_OPEN_PER_SERVICE = 5;
    private const STALE_BOOKING_MINUTES = 15;

    public static function ensureTable(): void
    {
        $schema = Capsule::schema();
        if ($schema->hasTable(self::TABLE)) {
            return;
        }
        $schema->create(self::TABLE, function ($table) {
            $table->increments('id');
            $table->integer('service_id')->unsigned()->index();
            $table->integer('client_id')->unsigned();
            $table->integer('customer_id')->unsigned();
            $table->integer('invoice_id')->unsigned()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('status', 16)->default('pending')->index();
            $table->integer('attempts')->unsigned()->default(0);
            $table->string('last_error', 255)->default('');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('booked_at')->nullable();
        });
    }

    /** The invoice text, also the booking text EchoCall stores (used to settle stuck rows). */
    public static function bookingText(int $invoiceId): string
    {
        return 'Top up, WHMCS invoice #' . $invoiceId;
    }

    /**
     * Issues an invoice for one of the configured amounts and returns its id.
     * An open invoice for the same amount is reused instead of piling up new ones.
     */
    public static function createInvoice(array $params, float $amount): int
    {
        self::ensureTable();
        $serviceId = (int)$params['serviceid'];
        $clientId = (int)$params['userid'];
        $customerId = EchoCallModule::customerId($params);
        if ($customerId <= 0) {
            throw new EchoCallApiException('No EchoCall customer is linked to this service yet.', 0, 'no_customer');
        }
        if (!in_array($amount, EchoCallModule::topupAmounts($params), true)) {
            throw new EchoCallApiException('This amount is not offered.', 0, 'invalid_amount');
        }
        if (self::clientCurrency($clientId) !== 'EUR') {
            throw new EchoCallApiException('Top ups are only available for accounts billed in EUR.', 0, 'currency');
        }

        $open = Capsule::table(self::TABLE . ' as t')
            ->join('tblinvoices as i', 'i.id', '=', 't.invoice_id')
            ->where('t.service_id', $serviceId)
            ->where('t.status', 'pending')
            ->where('i.status', 'Unpaid')
            ->select('t.invoice_id', 't.amount')
            ->get();
        foreach ($open as $row) {
            if (abs((float)$row->amount - $amount) < 0.001) {
                return (int)$row->invoice_id;
            }
        }
        if (count($open) >= self::MAX_OPEN_PER_SERVICE) {
            throw new EchoCallApiException('Please pay or cancel your open top up invoices first.', 0, 'too_many_open');
        }

        $today = date('Y-m-d');
        $result = localAPI('CreateInvoice', [
            'userid' => $clientId,
            'status' => 'Unpaid',
            'sendinvoice' => true,
            'date' => $today,
            'duedate' => $today,
            'itemdescription1' => sprintf('EchoCall credit (%s EUR)', number_format($amount, 2, ',', '.')),
            'itemamount1' => number_format($amount, 2, '.', ''),
            'itemtaxed1' => true,
            'notes' => 'EchoCall top up for service #' . $serviceId,
        ]);
        if (($result['result'] ?? '') !== 'success' || empty($result['invoiceid'])) {
            throw new EchoCallApiException('The invoice could not be created: ' . ($result['message'] ?? 'unknown error'), 0, 'invoice');
        }
        $invoiceId = (int)$result['invoiceid'];
        $now = date('Y-m-d H:i:s');
        Capsule::table(self::TABLE)->insert([
            'service_id' => $serviceId,
            'client_id' => $clientId,
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'amount' => number_format($amount, 2, '.', ''),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        EchoCallModule::activity(sprintf('Top up invoice #%d over %.2f EUR issued', $invoiceId, $amount), $serviceId, $clientId);
        return $invoiceId;
    }

    /** InvoicePaid: books the top up of this invoice, if it is one. */
    public static function onInvoicePaid(int $invoiceId): void
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            return;
        }
        $row = Capsule::table(self::TABLE)->where('invoice_id', $invoiceId)->first();
        if ($row && $row->status === 'pending') {
            self::book($row);
        }
    }

    /** InvoiceCancelled: an open top up of this invoice will never be paid. */
    public static function onInvoiceCancelled(int $invoiceId): void
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            return;
        }
        Capsule::table(self::TABLE)
            ->where('invoice_id', $invoiceId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** DailyCronJob: books paid invoices a hook missed and settles stuck rows. */
    public static function retryOpen(): void
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            return;
        }
        $staleBefore = date('Y-m-d H:i:s', time() - self::STALE_BOOKING_MINUTES * 60);
        $stuck = Capsule::table(self::TABLE)
            ->where('status', 'booking')
            ->where('updated_at', '<', $staleBefore)
            ->limit(50)
            ->get();
        foreach ($stuck as $row) {
            self::settleStuck($row);
        }

        $paid = Capsule::table(self::TABLE . ' as t')
            ->join('tblinvoices as i', 'i.id', '=', 't.invoice_id')
            ->where('t.status', 'pending')
            ->where('i.status', 'Paid')
            ->where('t.attempts', '<', 20)
            ->select('t.*')
            ->limit(100)
            ->get();
        foreach ($paid as $row) {
            self::book($row);
        }
    }

    /** Open and recent top ups of a service, newest first, for the client area. */
    public static function recent(int $serviceId, int $limit = 5): array
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            return [];
        }
        return Capsule::table(self::TABLE)
            ->where('service_id', $serviceId)
            ->whereIn('status', ['pending', 'booking', 'booked'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['invoice_id', 'amount', 'status', 'created_at', 'booked_at'])
            ->map(fn ($r) => [
                'invoiceId' => (int)$r->invoice_id,
                'amount' => (float)$r->amount,
                'status' => (string)$r->status,
                'createdAt' => (string)$r->created_at,
            ])
            ->all();
    }

    private static function book(object $row): void
    {
        $claimed = Capsule::table(self::TABLE)
            ->where('id', $row->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'booking',
                'attempts' => Capsule::raw('attempts + 1'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        if ($claimed !== 1) {
            return;
        }
        try {
            $params = EchoCallModule::paramsForService((int)$row->service_id);
            EchoCallModule::api($params)->post('/resellers/customers/' . (int)$row->customer_id . '/balance/add', [
                'amount' => (float)$row->amount,
                'description' => self::bookingText((int)$row->invoice_id),
            ]);
            self::markBooked($row);
        } catch (Throwable $e) {
            // Only a clear refusal goes back to pending. A timeout may still have
            // booked, so that row stays in "booking" and is settled later.
            $status = $e instanceof EchoCallApiException && $e->status >= 400 && $e->status < 500 ? 'pending' : 'booking';
            Capsule::table(self::TABLE)->where('id', $row->id)->update([
                'status' => $status,
                'last_error' => mb_substr($e->getMessage(), 0, 250),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            EchoCallModule::activity('Top up of invoice #' . (int)$row->invoice_id . ' not booked yet: ' . EchoCallModule::failure($e), (int)$row->service_id, (int)$row->client_id);
        }
    }

    private static function settleStuck(object $row): void
    {
        try {
            $params = EchoCallModule::paramsForService((int)$row->service_id);
            $list = EchoCallModule::api($params)->get('/resellers/customers/' . (int)$row->customer_id . '/transactions', ['limit' => 200]);
            $text = self::bookingText((int)$row->invoice_id);
            foreach (($list['data'] ?? []) as $tx) {
                if (is_array($tx) && ($tx['description'] ?? '') === $text) {
                    self::markBooked($row);
                    return;
                }
            }
            Capsule::table(self::TABLE)
                ->where('id', $row->id)
                ->where('status', 'booking')
                ->update(['status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            // EchoCall not reachable: leave the row as it is and look again tomorrow.
        }
    }

    private static function markBooked(object $row): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table(self::TABLE)->where('id', $row->id)->update([
            'status' => 'booked',
            'last_error' => '',
            'updated_at' => $now,
            'booked_at' => $now,
        ]);
        EchoCallModule::activity(sprintf('Top up of invoice #%d booked (%.2f EUR)', (int)$row->invoice_id, (float)$row->amount), (int)$row->service_id, (int)$row->client_id);
    }

    private static function clientCurrency(int $clientId): string
    {
        $code = Capsule::table('tblclients as c')
            ->join('tblcurrencies as cur', 'cur.id', '=', 'c.currency')
            ->where('c.id', $clientId)
            ->value('cur.code');
        return strtoupper((string)$code);
    }
}
