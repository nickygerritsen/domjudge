<?php declare(strict_types=1);

namespace DOMJudgeBundle\Controller\Team;

use Doctrine\ORM\EntityManagerInterface;
use DOMJudgeBundle\Controller\BaseController;
use DOMJudgeBundle\Entity\Clarification;
use DOMJudgeBundle\Entity\Language;
use DOMJudgeBundle\Form\Type\PrintType;
use DOMJudgeBundle\Service\DOMJudgeService;
use DOMJudgeBundle\Service\ScoreboardService;
use DOMJudgeBundle\Service\SubmissionService;
use DOMJudgeBundle\Utils\Printing;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class MiscController
 *
 * @Route("/team")
 * @Security("is_granted('ROLE_TEAM')")
 * @Security("user.getTeam() !== null", message="You do not have a team associated with your account. ")
 *
 * @package DOMJudgeBundle\Controller\Team
 */
class MiscController extends BaseController
{
    /**
     * @var DOMJudgeService
     */
    protected $DOMJudgeService;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ScoreboardService
     */
    protected $scoreboardService;

    /**
     * @var SubmissionService
     */
    protected $submissionService;

    /**
     * MiscController constructor.
     * @param DOMJudgeService        $DOMJudgeService
     * @param EntityManagerInterface $entityManager
     * @param ScoreboardService      $scoreboardService
     * @param SubmissionService      $submissionService
     */
    public function __construct(
        DOMJudgeService $DOMJudgeService,
        EntityManagerInterface $entityManager,
        ScoreboardService $scoreboardService,
        SubmissionService $submissionService
    ) {
        $this->DOMJudgeService   = $DOMJudgeService;
        $this->entityManager     = $entityManager;
        $this->scoreboardService = $scoreboardService;
        $this->submissionService = $submissionService;
    }

    /**
     * @Route("", name="team_index")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function homeAction(Request $request)
    {
        $user    = $this->DOMJudgeService->getUser();
        $team    = $user->getTeam();
        $teamId  = $team->getTeamid();
        $contest = $this->DOMJudgeService->getCurrentContest($teamId);

        $data = [
            'team' => $team,
            'contest' => $contest,
            'refresh' => [
                'after' => 30,
                'url' => $this->generateUrl('team_index'),
                'ajax' => true,
            ],
        ];
        if ($contest) {
            $data['scoreboard']           = $this->scoreboardService->getTeamScoreboard($contest, $teamId, true);
            $data['showFlags']            = $this->DOMJudgeService->dbconfig_get('show_flags', true);
            $data['showAffiliationLogos'] = $this->DOMJudgeService->dbconfig_get('show_affiliation_logos', false);
            $data['showAffiliations']     = $this->DOMJudgeService->dbconfig_get('show_affiliations', true);
            $data['showPending']          = $this->DOMJudgeService->dbconfig_get('show_pending', false);
            $data['showTeamSubmissions']  = $this->DOMJudgeService->dbconfig_get('show_teams_submissions', true);
            $data['scoreInSeconds']       = $this->DOMJudgeService->dbconfig_get('score_in_seconds', false);
            $data['verificationRequired'] = $this->DOMJudgeService->dbconfig_get('verification_required', false);
            $data['limitToTeams']         = [$team];
            // We need to clear the entity manager, because loading the team scoreboard seems to break getting submission
            // contestproblems for the contest we get the scoreboard for
            $this->entityManager->clear();
            $data['submissions'] = $this->submissionService->getSubmissionList([$contest->getCid() => $contest],
                                                                               ['teamid' => $teamId], 0)[0];

            /** @var Clarification[] $clarifications */
            $clarifications = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Clarification', 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender IS NULL')
                ->andWhere('c.recipient = :team OR c.recipient IS NULL')
                ->setParameter(':contest', $contest)
                ->setParameter(':team', $team)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();

            /** @var Clarification[] $clarificationRequests */
            $clarificationRequests = $this->entityManager->createQueryBuilder()
                ->from('DOMJudgeBundle:Clarification', 'c')
                ->leftJoin('c.problem', 'p')
                ->leftJoin('c.sender', 's')
                ->leftJoin('c.recipient', 'r')
                ->select('c', 'p')
                ->andWhere('c.contest = :contest')
                ->andWhere('c.sender = :team')
                ->setParameter(':contest', $contest)
                ->setParameter(':team', $team)
                ->addOrderBy('c.submittime', 'DESC')
                ->addOrderBy('c.clarid', 'DESC')
                ->getQuery()
                ->getResult();

            $data['clarifications']        = $clarifications;
            $data['clarificationRequests'] = $clarificationRequests;
            $data['categories']            = $this->DOMJudgeService->dbconfig_get('clar_categories');
        }

        if ($request->isXmlHttpRequest()) {
            $data['ajax'] = true;
            return $this->render('@DOMJudge/team/partials/index_content.html.twig', $data);
        }

        return $this->render('@DOMJudge/team/index.html.twig', $data);
    }

    /**
     * @Route("/change-contest/{contestId}", name="team_change_contest")
     * @param Request         $request
     * @param RouterInterface $router
     * @param int             $contestId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function changeContestAction(Request $request, RouterInterface $router, int $contestId)
    {
        if ($this->isLocalReferrer($router, $request)) {
            $response = new RedirectResponse($request->headers->get('referer'));
        } else {
            $response = $this->redirectToRoute('public_index');
        }
        return $this->DOMJudgeService->setCookie('domjudge_cid', (string)$contestId, 0, null, '', false, false,
                                                 $response);
    }

    /**
     * @Route("/print", name="team_print")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function printAction(Request $request)
    {
        if (!$this->DOMJudgeService->dbconfig_get('enable_printing', 0)) {
            throw new AccessDeniedHttpException("Printing disabled in config");
        }

        $form = $this->createForm(PrintType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var UploadedFile $file */
            $file             = $data['code'];
            $realfile         = $file->getRealPath();
            $originalfilename = $file->getClientOriginalName() ?? '';

            $langid   = $data['langid'];
            $username = $this->getUser()->getUsername();

            $team = $this->DOMJudgeService->getUser()->getTeam();
            $ret  = Printing::send($realfile, $originalfilename, $langid, $username, $team->getName(),
                                   $team->getRoom());

            return $this->render('@DOMJudge/team/print_result.html.twig', [
                'success' => $ret[0],
                'output' => $ret[1],
            ]);
        }

        /** @var Language[] $languages */
        $languages = $this->entityManager->createQueryBuilder()
            ->from('DOMJudgeBundle:Language', 'l')
            ->select('l')
            ->andWhere('l.allowSubmit = 1')
            ->getQuery()
            ->getResult();

        return $this->render('@DOMJudge/team/print.html.twig', [
            'form' => $form->createView(),
            'languages' => $languages,
        ]);
    }
}
