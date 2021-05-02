<?php

declare(strict_types = 1);

namespace App\Controller\Category;

use App\Entity\Category;
use App\Entity\Role;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShowController extends AbstractController {
	/**
	 * @Route("/categories/{category_uuid}/show", name="category-show", methods="GET")
	 * @ParamConverter("category", class="App\Entity\Category", options={"mapping": {"category_uuid" = "uuid"}})
	 */
	public function __invoke(Category $category): Response {
		if ($this->isGranted(Role::ROLE_TEST_USER)) {
			return $this->showcaseShow($category);
		}
		//todo for now use the same method because no difference exists for category show for test user
		if ($this->isGranted(Role::ROLE_PROJECT_LEAD)) {
			return $this->showcaseShow($category);
		}
		throw $this->createNotFoundException();
	}
	
	private function showcaseShow(Category $category): Response {
		return $this->render('categories/show.html.twig', ['category' => $category]);
	}
}