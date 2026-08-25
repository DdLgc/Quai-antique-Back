<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\{Fixture, FixtureGroupInterface};
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if ($this->userRepository->findOneBy(['email' => 'admin@quai.fr'])) {
            return;
        }

        $admin = (new User())
            ->setFirstName('Admin')
            ->setLastName('Quai Antique')
            ->setGuestNumber(2)
            ->setEmail('admin@quai.fr')
            ->setRoles(['ROLE_ADMIN'])
            ->setCreatedAt(new DateTimeImmutable());

        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'AdminTest1')
        );

        $manager->persist($admin);
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['admin'];
    }
}