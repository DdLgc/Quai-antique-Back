<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\RestaurantRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reservations', name: 'app_api_reservation_')]
class ReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private ReservationRepository $repository,
        private RestaurantRepository $restaurantRepository,
    ) {
    }

    #[Route(methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = $request->toArray();

        if (
            empty($data['date']) ||
            empty($data['time']) ||
            !isset($data['guestNumber'])
        ) {
            return new JsonResponse(
                ['message' => 'Données de réservation incomplètes'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date']);
        $time = DateTimeImmutable::createFromFormat('!H:i', $data['time']);

        if (!$date || !$time) {
            return new JsonResponse(
                ['message' => 'Date ou heure invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $guestNumber = (int) $data['guestNumber'];

        if ($guestNumber < 1) {
            return new JsonResponse(
                ['message' => 'Le nombre de convives doit être supérieur à zéro'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $restaurant = $this->restaurantRepository->find(1);

        if (!$restaurant) {
            return new JsonResponse(
                ['message' => 'Restaurant introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($guestNumber > $restaurant->getMaxGuest()) {
            return new JsonResponse(
                [
                    'message' => sprintf(
                        'Le nombre maximal de convives est de %d',
                        $restaurant->getMaxGuest()
                    )
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $slots = $this->getAvailability(
            $restaurant,
            $date,
            $guestNumber
        );

        $selectedSlot = null;

        foreach ($slots as $slot) {
            if ($slot['time'] === $data['time']) {
                $selectedSlot = $slot;
                break;
            }
        }

        if (!$selectedSlot) {
            return new JsonResponse(
                ['message' => 'Créneau horaire invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$selectedSlot['available']) {
            return new JsonResponse(
                ['message' => 'Il ne reste pas assez de places pour ce service'],
                Response::HTTP_CONFLICT
            );
        }

        $reservation = (new Reservation())
            ->setDate($date)
            ->setTime($time)
            ->setGuestNumber($guestNumber)
            ->setAllergy($data['allergy'] ?? null)
            ->setUser($user instanceof User ? $user : null);

        $this->manager->persist($reservation);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id' => $reservation->getId(),
                'date' => $reservation->getDate()?->format('Y-m-d'),
                'time' => $reservation->getTime()?->format('H:i'),
                'guestNumber' => $reservation->getGuestNumber(),
                'allergy' => $reservation->getAllergy(),
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/availability', name: 'availability', methods: ['GET'])]
    public function availability(Request $request): JsonResponse
    {
        $dateValue = $request->query->get('date');
        $guestNumber = (int) $request->query->get('guestNumber', 1);

        if (!$dateValue || $guestNumber < 1) {
            return new JsonResponse(
                ['message' => 'Date ou nombre de convives invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dateValue
        );

        if (!$date) {
            return new JsonResponse(
                ['message' => 'Date invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $restaurant = $this->restaurantRepository->find(1);

        if (!$restaurant) {
            return new JsonResponse(
                ['message' => 'Restaurant introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($guestNumber > $restaurant->getMaxGuest()) {
            return new JsonResponse(
                ['message' => 'Nombre de convives supérieur à la capacité du restaurant'],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse([
            'date' => $date->format('Y-m-d'),
            'maxGuest' => $restaurant->getMaxGuest(),
            'slots' => $this->getAvailability(
                $restaurant,
                $date,
                $guestNumber
            ),
        ]);
    }

    #[Route(methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Utilisateur non authentifié'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $reservations = $this->repository->findBy(
            ['user' => $user],
            ['date' => 'ASC', 'time' => 'ASC']
        );

        $data = array_map(
            static fn (Reservation $reservation) => [
                'id' => $reservation->getId(),
                'date' => $reservation->getDate()?->format('Y-m-d'),
                'time' => $reservation->getTime()?->format('H:i'),
                'guestNumber' => $reservation->getGuestNumber(),
                'allergy' => $reservation->getAllergy(),
            ],
            $reservations
        );

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Utilisateur non authentifié'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $reservation = $this->repository->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$reservation) {
            return new JsonResponse(
                ['message' => 'Réservation introuvable'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->manager->remove($reservation);
        $this->manager->flush();

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT
        );
    }

    private function getAvailability(
        Restaurant $restaurant,
        DateTimeImmutable $date,
        int $requestedGuests
    ): array {
        $dayKey = strtolower($date->format('l'));
        $weeklyHours = $restaurant->getWeeklyOpeningHours();
        $dayHours = $weeklyHours[$dayKey] ?? null;

        if (!$dayHours) {
            return [];
        }

        $amSlots = $this->generateSlots(
            $dayHours['am'] ?? null
        );

        $pmSlots = $this->generateSlots(
            $dayHours['pm'] ?? null
        );

        $reservations = $this->repository->findBy([
            'date' => $date
        ]);

        $reservedGuests = [
            'am' => 0,
            'pm' => 0,
        ];

        foreach ($reservations as $reservation) {
            $reservationTime = $reservation->getTime();

            if (!$reservationTime) {
                continue;
            }

            $period = (int) $reservationTime->format('H') < 18
                ? 'am'
                : 'pm';

            $reservedGuests[$period] +=
                $reservation->getGuestNumber();
        }

        $slots = [];

        foreach ($amSlots as $time) {
            $remaining = max(
                0,
                $restaurant->getMaxGuest() - $reservedGuests['am']
            );

            $slots[] = [
                'time' => $time,
                'remainingGuests' => $remaining,
                'available' => $remaining >= $requestedGuests,
            ];
        }

        foreach ($pmSlots as $time) {
            $remaining = max(
                0,
                $restaurant->getMaxGuest() - $reservedGuests['pm']
            );

            $slots[] = [
                'time' => $time,
                'remainingGuests' => $remaining,
                'available' => $remaining >= $requestedGuests,
            ];
        }

        return $slots;
    }

    private function generateSlots(?array $period): array
    {
        if (
            !$period ||
            count($period) !== 2 ||
            empty($period[0]) ||
            empty($period[1])
        ) {
            return [];
        }

        $opening = DateTimeImmutable::createFromFormat(
            '!H:i',
            $period[0]
        );

        $closing = DateTimeImmutable::createFromFormat(
            '!H:i',
            $period[1]
        );

        if (!$opening || !$closing || $opening >= $closing) {
            return [];
        }

        $lastReservation = $closing->modify('-1 hour');
        $slots = [];

        for (
            $slot = $opening;
            $slot <= $lastReservation;
            $slot = $slot->modify('+15 minutes')
        ) {
            $slots[] = $slot->format('H:i');
        }

        return $slots;
    }
}