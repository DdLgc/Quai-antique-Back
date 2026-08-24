<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
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
    ) {
    }

    #[Route(methods: 'POST')]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Utilisateur non authentifié'],
                Response::HTTP_UNAUTHORIZED
            );
        }

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

        $reservation = (new Reservation())
            ->setDate($date)
            ->setTime($time)
            ->setGuestNumber($guestNumber)
            ->setAllergy($data['allergy'] ?? null)
            ->setUser($user);

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

    #[Route(methods: 'GET')]
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

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['message' => 'Utilisateur non authentifié'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        /**
         *Auth control (not do $this->repository->find($id);) user 2 can supress reservation 1
         */
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

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}