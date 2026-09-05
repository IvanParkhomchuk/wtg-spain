<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, Offer $offer): JsonResponse
    {
        $data = $request->validated();

        $reservation = DB::transaction(function () use ($offer, $data) {
            $offer = Offer::lockForUpdate()->findOrFail($offer->id);

            if ($offer->available_units <= 0) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'No available units');
            }

            $offer->decrement('available_units');

            return Reservation::create([
                'offer_id' => $offer->id,
                'client_reference' => $data['client_reference'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
            ]);
        });

        return ReservationResource::make($reservation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
