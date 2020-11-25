<?php

namespace KimaiPlugin\ChromePluginBundle\Tests;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\ProjectFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\TimesheetFieldSubscriber;

/**
 * Defines the sample data to load in during controller tests.
 */
final class DataFixtures extends Fixture
{
    const SEED = 1;

    /**
     * @var string
     */
    private string $startDate = '2018-04-01';

    /**
     * @var ObjectManager
     */
    private ObjectManager $manager;

    /**
     * @var Generator
     */
    private Generator $faker;

    private array $activities;

    public function __construct()
    {
        srand(self::SEED);
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $this->faker = Factory::create();
        $this->manager = $manager;

        $users = $manager->getRepository(User::class)->findAll();

        // Create customer
        $customer = new Customer();
        $customer
            ->setCurrency($this->faker->currencyCode)
            ->setName($this->faker->company)
            ->setAddress($this->faker->address)
            ->setComment($this->faker->text)
            ->setNumber('C-' . $this->faker->ean8)
            ->setCountry($this->faker->countryCode)
            ->setTimezone($this->faker->timezone);
        $manager->persist($customer);

        $this->activities = [];

        // Create projects
        $projects = [];
        for ($i = 0; $i < count(BaseControllerTest::PROJECT_NAMES); $i++) {
            $project = new Project();
            $project
                ->setName($this->faker->catchPhrase)
                ->setCustomer($customer);
            $projects[] = $project;

            $projectMeta = new ProjectMeta();
            $projectMeta->setName(ProjectFieldSubscriber::PROJECT_ID);
            $projectMeta->setValue(BaseControllerTest::PROJECT_NAMES[$i]);
            $this->manager->persist($projectMeta);

            $project->setMetaField($projectMeta);
            $this->manager->persist($project);

            // Create activities for the first project
            $this->activities[$project->getName()] = $this->createActivities(rand(3, 8), $project);
        }
        // Create global activities
        $this->activities["Global"] = $this->createActivities(rand(3, 8));

        // Create time sheets
        $timeSheetCount = rand(10, 20);
        for ($i = 0; $i < $timeSheetCount; $i++) {
            $timeSheet = new Timesheet();
            $project = $projects[array_rand($projects)];
            $timeSheet
                ->setActivity($this->getRandomActivity(rand(1,4) > 1 ? $project : null))
                ->setProject($project)
                ->setDescription($this->faker->text)
                ->setUser($users[array_rand($users)])
                ->setBegin($this->getStartDate())
                ->setDuration(rand(1,12) * 15);
            $this->manager->persist($timeSheet);
            $timeSheetsMeta = new TimesheetMeta();
            $timeSheetsMeta->setName(TimesheetFieldSubscriber::ISSUE_ID);
            $issueId = $project->getMetaField(ProjectFieldSubscriber::PROJECT_ID)->getValue() . ":" . rand(1,3);
            $timeSheetsMeta->setValue($issueId);
            $this->manager->persist($timeSheetsMeta);

            $timeSheet->setMetaField($timeSheetsMeta);
            $this->manager->persist($timeSheet);
        }

        $manager->flush();
    }

    protected function createActivities(int $count, Project $project = null): array
    {
        $activities = [];
        for ($i = 0; $i < $count; $i++) {
            $activity = new Activity();
            $activity
                ->setProject($project)
                ->setName($this->faker->bs)
                ->setComment($this->faker->text);
            $this->manager->persist($activity);
            $activities[] = $activity;
        }
        return $activities;
    }

    private function getRandomActivity(?Project $project): Activity
    {
        if ($project) {
            return $this->activities[$project->getName()][array_rand($this->activities[$project->getName()])];
        } else {
            return $this->activities["Global"][array_rand($this->activities["Global"])];
        }
    }

    private function getStartDate()
    {
        $start = \DateTime::createFromFormat('Y-m-d', $this->startDate);
        $start->modify("+ " . rand(0, 60) . " days");
        $start->modify('+ ' . rand(1, 86400) . ' seconds');
        return $start;
    }
}
