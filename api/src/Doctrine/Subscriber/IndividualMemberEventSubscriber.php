<?php

namespace App\Doctrine\Subscriber;

use App\Doctrine\Module\ORMEventSubscriber;
use App\Entity\Event;
use App\Entity\Organisation\IndividualMember;
use App\Entity\Person\Person;
use App\Util\AppUtil;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Bridge\Doctrine\RegistryInterface;

class IndividualMemberEventSubscriber implements ORMEventSubscriber
{

    private $registry;
    private $manager;

    function __construct(RegistryInterface $registry, EntityManagerInterface $manager)
    {
        $this->registry = $registry;
        $this->manager = $manager;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
            Events::postLoad,
        ];
    }

    private function preUpdateData(IndividualMember $member)
    {

    }

    private function postUpdateData(IndividualMember $member)
    {
        if (!empty($email = $member->getEmail())) {
            $person = $member->getPerson();
            $personRepo = $this->registry->getRepository(Person::class);
            $manager = $this->manager;
            $personWithEmail = $personRepo->findOneBy(['email' => $email,
            ]);
            if (!empty($personWithEmail)) {
                $person->removeIndividualMember($member);
                $personWithEmail->addIndividualMember($member);
                $member->setPerson($personWithEmail);
                $manager->persist($person);
                $personWithEmail->preSave();
                $manager->persist($personWithEmail);

                if (!empty($userWithPersonEmail = $personWithEmail->getUser())) {
                    $userWithPersonEmail->setUpdatedAt(new \DateTime());
                    if (!empty($password)) {
                        $userWithPersonEmail->setPlainPassword($password);
                    }
                    $manager->persist($userWithPersonEmail);
                }

                $personWithEmailExisting = true;
            } else {
                $person->setEmail($email);
                $person->getUser()->setEmail($email);
                $manager->persist($person);
                $manager->persist($person->getUser());
            }
            $manager->flush();
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof IndividualMember) return;
        $this->updateData($object);
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof IndividualMember) return;
        $this->updateData($object);
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof IndividualMember) return;

        $ar = [
            'data' => [
                'individualMember' => [
                    'uuid' => $object->getUuid(),
                    'accessToken' => $object->getAccessToken(),
                    'personUuid' => $object->getPersonUuid(),
                    'organisationUuid' => $object->getOrganisationUuid(),
//                    '_SYSTEM_OPERATION' => Message::OPERATION_POST,
                ]
            ],
            'version' => AppUtil::MESSAGE_VERSION,
        ];
        $this->postUpdateData();
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof IndividualMember) return;

        $ar = [
            'data' => [
                'individualMember' => [
                    'uuid' => $object->getUuid(),
                    'accessToken' => $object->getAccessToken(),
                    'personUuid' => $object->getPersonUuid(),
                    'organisationUuid' => $object->getOrganisationUuid(),
//                    '_SYSTEM_OPERATION' => Message::OPERATION_PUT,
                ]
            ],
            'version' => AppUtil::MESSAGE_VERSION,
        ];

        $this->postUpdateData();
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $object = $args->getObject();
        if (!$object instanceof IndividualMember) {
            return;
        }
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        /** @var IndividualMember $object */
        $object = $args->getObject();
        if (!$object instanceof IndividualMember) {
            return;
        }
        if (!empty($person = $object->getPerson())) {
            if (empty($person->getName())) {
                $person->combineData();
            }
        }
        return;
    }
}
