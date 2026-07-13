<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Application\Booking\CreateBooking;
use QSManager\Application\Booking\CreateBookingCommand;
use QSManager\Application\Booking\ListBookings;
use QSManager\Application\Booking\SyncBookingToGas;
use QSManager\Domain\Booking\BookingRepository;
use QSManager\Interfaces\Http\Validation\BookingRequestValidator;
use QSManager\Interfaces\Http\Validation\ValidationException;
use Slim\App;

final class BookingController
{
    public function __construct(
        private readonly CreateBooking $createBooking,
        private readonly ListBookings $listBookings,
        private readonly BookingRequestValidator $validator,
        private readonly SyncBookingToGas $syncBookingToGas,
        private readonly BookingRepository $bookings,
    ) {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/bookings', [$this, 'index']);
        $app->post('/api/v1/bookings', [$this, 'store']);
        $app->put('/api/v1/bookings/{id}', [$this, 'update']);
        $app->delete('/api/v1/bookings/{id}', [$this, 'delete']);
        $app->post('/api/v1/bookings/{id}/sync-gas', [$this, 'syncGas']);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $bookings = array_map(
            static fn ($booking): array => $booking->toArray(),
            $this->listBookings->execute()
        );

        return $this->json($response, ['bookings' => $bookings]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        try {
            $validated = $this->validator->validate($body);
            $booking = $this->createBooking->execute(new CreateBookingCommand(
                $validated['service_id'],
                $validated['staff_id'],
                $validated['customer_name'],
                $validated['customer_phone'],
                $validated['scheduled_for'],
                $validated['status'],
                $validated['address'],
                $validated['comuna'],
                $validated['service_value'],
                $validated['transfer_value'],
                $validated['deposit_amount'],
                $validated['total_service'],
                $validated['balance_due'],
                $validated['payment_status'],
                $validated['service_status'],
                $validated['contract_id'],
                $validated['milestone'],
                $validated['cash_group'],
            ));
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23503') {
                return $this->json($response, ['error' => 'Selected service or staff member does not exist.'], 422);
            }
            throw $exception;
        }

        $warning = null;
        try {
            $syncResult = $this->syncBookingToGas->execute($booking->id()->value());
            if ($syncResult->status() === 'failed') {
                $warning = 'GAS sync failed: ' . $syncResult->message();
            }
        } catch (\Exception $e) {
            $warning = 'GAS sync error: ' . $e->getMessage();
        }

        $responseData = ['booking' => $booking->toArray()];
        if ($warning !== null) {
            $responseData['warning'] = $warning;
        }

        return $this->json($response, $responseData, 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return $this->json($response, ['error' => 'Booking id must be a positive integer.'], 422);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        try {
            $validated = $this->validator->validate($body);
            $booking = $this->bookings->update($id, $validated);
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23503') {
                return $this->json($response, ['error' => 'Selected service or staff member does not exist.'], 422);
            }
            throw $exception;
        }

        if ($booking === null) {
            return $this->json($response, ['error' => 'Booking not found.'], 404);
        }

        $warning = null;
        try {
            $syncResult = $this->syncBookingToGas->execute($booking->id()->value());
            if ($syncResult->status() === 'failed') {
                $warning = 'GAS sync failed: ' . $syncResult->message();
            }
        } catch (\Exception $e) {
            $warning = 'GAS sync error: ' . $e->getMessage();
        }

        $responseData = ['booking' => $booking->toArray()];
        if ($warning !== null) {
            $responseData['warning'] = $warning;
        }

        return $this->json($response, $responseData);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return $this->json($response, ['error' => 'Booking id must be a positive integer.'], 422);
        }

        try {
            if (!$this->bookings->delete($id)) {
                return $this->json($response, ['error' => 'Booking not found.'], 404);
            }
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23503') {
                return $this->json($response, [
                    'error' => 'Booking has related records and cannot be deleted.',
                ], 409);
            }
            throw $exception;
        }

        return $this->json($response, ['deleted' => true]);
    }

    public function syncGas(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = filter_var($args['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || (int) $id <= 0) {
            return $this->json($response, ['error' => 'Booking id must be a positive integer.'], 422);
        }

        try {
            $result = $this->syncBookingToGas->execute((int) $id);
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 404);
        }

        return $this->json($response, [
            'sync' => $result->toArray(),
        ], $result->success() ? 200 : 202);
    }

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function validationError(ResponseInterface $response, array $errors): ResponseInterface
    {
        return $this->json($response, [
            'error' => 'Validation failed.',
            'errors' => $errors,
        ], 422);
    }

    private function positiveId(mixed $value): ?int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
