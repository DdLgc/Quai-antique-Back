<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $time = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $guestNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $allergy = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getTime(): ?\DateTimeImmutable
    {
        return $this->time;
    }

    public function setTime(\DateTimeImmutable $time): static
    {
        $this->time = $time;

        return $this;
    }

    public function getGuestNumber(): ?int
    {
        return $this->guestNumber;
    }

    public function setGuestNumber(int $guestNumber): static
    {
        $this->guestNumber = $guestNumber;

        return $this;
    }

    public function getAllergy(): ?string
    {
        return $this->allergy;
    }

    public function setAllergy(?string $allergy): static
    {
        $this->allergy = $allergy;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
    #[Route('/{id}/capacity', name: 'update_capacity', methods: ['PUT'])]
    public function updateCapacity(
        Restaurant $restaurant,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $maxGuest = (int) ($data['maxGuest'] ?? 0);

        if ($maxGuest < 1) {
            return new JsonResponse(
                ['message' => 'La capacité doit être supérieure à 0'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $restaurant->setMaxGuest($maxGuest);

        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Capacité mise à jour',
            'maxGuest' => $restaurant->getMaxGuest()
        ]);
    }
}
