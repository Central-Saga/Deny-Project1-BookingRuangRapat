<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateBookingDTO;
use App\DTOs\UpdateBookingDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\RejectBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Contracts\BookingServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(
        protected BookingServiceInterface $bookingService
    ) {}

    public function index(Request $request)
    {
        $p = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $bookings = $this->bookingService->getBookings(
            $request->user(),
            $request->filled('date') ? $request->string('date')->toString() : null,
            $request->filled('room_id') ? $request->integer('room_id') : null,
            (int) ($p['per_page'] ?? 25)
        );

        return BookingResource::collection($bookings)->additional(['success' => true, 'status' => true, 'message' => 'Data booking berhasil dimuat.', 'errors' => []]);
    }

    public function store(StoreBookingRequest $request)
    {
        $dto = CreateBookingDTO::fromRequest($request);
        $booking = $this->bookingService->createBooking($request->user(), $dto);

        return (new BookingResource($booking))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function availability(Request $request)
    {
        $p = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $bookings = $this->bookingService->getAvailability((int) ($p['per_page'] ?? 25));

        return BookingResource::collection($bookings)->additional(['success' => true, 'status' => true, 'message' => 'Data ketersediaan berhasil dimuat.', 'errors' => []]);
    }

    public function show(Booking $booking)
    {
        Gate::authorize('view', $booking);

        return (new BookingResource($booking->load(['room', 'user', 'approver'])))->additional(['status' => true, 'message' => 'Data booking berhasil dimuat.', 'errors' => []]);
    }

    public function updateCollection(UpdateBookingRequest $request)
    {
        $id = $request->input('id') ?? $request->input('booking_id');
        if ($id && ($booking = $this->bookingService->findBooking((int) $id))) {
            return $this->update($request, $booking);
        }
        abort(422, 'ID booking harus disertakan.');
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        Gate::authorize('update', $booking);
        $dto = UpdateBookingDTO::fromRequest($request);
        $updatedBooking = $this->bookingService->updateBooking($booking, $dto);

        return (new BookingResource($updatedBooking))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function approve(Booking $booking)
    {
        Gate::authorize('approve', $booking);
        $approvedBooking = $this->bookingService->approveBooking($booking, (int) auth()->id());

        return (new BookingResource($approvedBooking))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function reject(RejectBookingRequest $request, Booking $booking)
    {
        Gate::authorize('reject', $booking);
        $rejectedBooking = $this->bookingService->rejectBooking($booking, (int) auth()->id(), $request->validated()['approval_notes']);

        return (new BookingResource($rejectedBooking))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking)
    {
        Gate::authorize('cancel', $booking);
        $cancelledBooking = $this->bookingService->cancelBooking($booking, $request->validated()['cancellation_reason']);

        return (new BookingResource($cancelledBooking))->additional(['status' => true, 'message' => 'Operasi booking berhasil.', 'errors' => []]);
    }

    public function destroy(Booking $booking)
    {
        Gate::authorize('delete', $booking);
        $this->bookingService->deleteBooking($booking);

        return response()->noContent();
    }
}
