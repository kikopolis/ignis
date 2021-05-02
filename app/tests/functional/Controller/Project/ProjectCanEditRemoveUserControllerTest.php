<?php

declare(strict_types = 1);

namespace App\Tests\functional\Controller\Project;

use App\Entity\User;
use App\Tests\BaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use function sprintf;

/**
 * @covers \App\Controller\Project\CanEditRemoveUserController
 */
class ProjectCanEditRemoveUserControllerTest extends BaseWebTestCase {
	public function testRemove(): void {
		$user    = $this->getOneActiveUser();
		$project = $this->getOneProject();
		/** @var User $lead */
		$lead = $project->getAuthor();
		$this->client->loginUser($lead);
		$this->client->request(Request::METHOD_GET, sprintf('/projects/%s/can-edit/remove/%s', $project->getUuid(), $user->getUuid()));
		$this->client->followRedirect();
		static::assertResponseIsSuccessful();
	}
}