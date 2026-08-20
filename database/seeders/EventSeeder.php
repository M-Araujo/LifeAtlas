<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventSeeder extends Seeder {
    public function run(): void {
        $lifeAreas = DB::table('life_areas')->pluck('id', 'name');

        $templates = [
            'Social' => [
                ['Coffee with a friend', 'Caught up over coffee after work.', 'positive'],
                ['Family dinner', 'Spent the evening with family.', 'positive'],
                ['Birthday celebration', 'Celebrated a friend\'s birthday.', 'positive'],
                ['Cancelled plans', 'Plans were cancelled at the last minute.', 'negative'],
                ['Video call with a friend', 'Stayed connected despite the distance.', 'positive'],
                ['Community event', 'Attended a local community gathering.', 'neutral'],
            ],

            'Professional' => [
                ['Completed a project milestone', 'Finished an important feature at work.', 'positive'],
                ['Updated portfolio', 'Improved my personal developer portfolio.', 'positive'],
                ['Job application submitted', 'Applied for a new opportunity.', 'positive'],
                ['Long workday', 'Worked overtime to meet a deadline.', 'negative'],
                ['Team meeting', 'Weekly planning and collaboration session.', 'neutral'],
                ['Learned a new Laravel feature', 'Explored a new backend concept.', 'positive'],
            ],

            'Personal' => [
                ['Morning workout', 'Completed a strength training session.', 'positive'],
                ['Read a book', 'Finished several chapters of a personal growth book.', 'positive'],
                ['Meditation session', 'Practised mindfulness for 20 minutes.', 'positive'],
                ['Poor sleep', 'Slept less than 6 hours.', 'negative'],
                ['Weekend walk', 'Spent time outdoors to recharge.', 'positive'],
                ['House organization', 'Decluttered and organized my workspace.', 'neutral'],
            ],

            'Academic' => [
                ['Studied Laravel', 'Worked on migrations and seeders.', 'positive'],
                ['Completed a coding challenge', 'Solved an algorithm exercise.', 'positive'],
                ['Math practice', 'Studied Singapore Math for one hour.', 'positive'],
                ['Missed a study session', 'Did not follow the planned schedule.', 'negative'],
                ['Watched a programming lecture', 'Learned about software architecture.', 'positive'],
                ['Reviewed TypeScript', 'Practised typing and interfaces.', 'neutral'],
            ],

            'Hobbies' => [
                ['Photography walk', 'Captured nature and street photos.', 'positive'],
                ['Aquarium maintenance', 'Cleaned the aquarium and checked water quality.', 'neutral'],
                ['Played piano', 'Practised favorite pieces for 30 minutes.', 'positive'],
                ['Worked on a creative project', 'Spent the evening drawing and designing.', 'positive'],
                ['Skipped hobby time', 'Felt too tired for creative work.', 'negative'],
                ['Gardening', 'Repotted herbs and watered plants.', 'positive'],
            ],
        ];

        $events = [];

        foreach ($lifeAreas as $areaName => $areaId) {

            for ($month = 0; $month < 5; $month++) {

                $count = rand(1, 10);

                for ($i = 0; $i < $count; $i++) {

                    $template = fake()->randomElement($templates[$areaName]);

                    $events[] = [
                        'description' => $template[1],
                        'type' => $template[2],
                        'life_area_id' => $areaId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        DB::table('events')->insert($events);
    }
}
