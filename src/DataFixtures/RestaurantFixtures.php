<?php

namespace App\DataFixtures;

use App\Entity\Restaurant;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Faker;

class RestaurantFixtures extends Fixture
{
    public const RESTAURANT_REFERENCE = 'restaurant-';
    public const RESTAURANT_NB_TUPLES = 10;

    /** @throws Exception */
    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create();

        for ($i = 1; $i <= self::RESTAURANT_NB_TUPLES; $i++) {
            $restaurant = (new Restaurant())
                ->setName("Restaurant n°$i")
                ->setDescription($faker->text())
                ->setAmOpeningTime([
                    '12:00',
                    '12:15',
                    '12:30',
                    '12:45',
                    '13:00',
                    '13:15',
                ])
                ->setPmOpeningTime([
                    '19:00',
                    '19:15',
                    '19:30',
                    '19:45',
                    '20:00',
                    '20:15',
                    '20:30',
                    '20:45',
                    '21:00',
                ])
                ->setWeeklyOpeningHours([
                    'monday' => [
                        'am' => ['12:00', '14:00'],
                        'pm' => ['19:00', '22:00'],
                    ],
                    'tuesday' => [
                        'am' => ['12:00', '14:00'],
                        'pm' => ['19:00', '22:00'],
                    ],
                    'wednesday' => [
                        'am' => null,
                        'pm' => null,
                    ],
                    'thursday' => [
                        'am' => ['12:00', '14:00'],
                        'pm' => ['19:00', '22:00'],
                    ],
                    'friday' => [
                        'am' => ['12:00', '14:00'],
                        'pm' => ['19:00', '22:30'],
                    ],
                    'saturday' => [
                        'am' => ['12:00', '14:30'],
                        'pm' => ['19:00', '22:30'],
                    ],
                    'sunday' => [
                        'am' => ['12:00', '14:30'],
                        'pm' => null,
                    ],
                ])
                ->setMaxGuest(random_int(10, 50))
                ->setCreatedAt(new DateTimeImmutable());

            $manager->persist($restaurant);
            $this->addReference(self::RESTAURANT_REFERENCE . $i, $restaurant);
        }

        $manager->flush();
    }
}
