<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Monarc\Core\Entity\DeliveriesModels;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Service\DeliverableGenerationService;
use Monarc\FrontOffice\Table\ReportSubmissionTable;

/**
 * Rapport préparé : le PDF est généré et gardé de côté pour que l'utilisateur le relise, puis c'est ce fichier,
 * et lui seul, qui est envoyé au BO. Une analyse n'en a qu'un à la fois ; il expire au bout d'une heure et n'est
 * utilisable qu'une fois.
 */
class ReportDraftService
{
    private const VALIDITY_IN_SECONDS = 3600;

    public function __construct(
        private DeliverableGenerationService $deliverableGenerationService,
        private ReportSubmissionTable $reportSubmissionTable
    ) {
    }

    /**
     * Génère le rapport final de l'analyse en PDF et le garde de côté, à la place du précédent.
     *
     * @return array{token: string, fileSize: int, createdAt: string, expiresAt: string}
     */
    public function prepare(Anr $anr): array
    {
        $directory = $this->getDirectory($anr);
        $this->removeExpired();
        $this->removeAll($directory);

        $generatedPath = $this->deliverableGenerationService->generateDeliverableWithValues(
            $anr,
            DeliveriesModels::MODEL_RISK_ANALYSIS,
            [
                'format' => 'pdf',
                'status' => 1,
                'docname' => $anr->getLabel(),
                'version' => (string)(\count($this->reportSubmissionTable->findNewestFirstByAnr($anr)) + 1),
            ]
        )['path'];

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new Exception(sprintf('The directory "%s" was not created.', $directory));
        }
        $token = bin2hex(random_bytes(16));
        $path = $this->getPath($directory, $token);
        // Le dossier temporaire et le volume de données peuvent être sur deux disques : copie puis suppression
        $isCopied = copy($generatedPath, $path);
        unlink($generatedPath);
        if (!$isCopied) {
            throw new Exception('The prepared report could not be saved.');
        }

        return $this->getMetadata($token, $path);
    }

    /**
     * Rapport préparé de l'analyse s'il est encore valable, pour que l'utilisateur le retrouve en rouvrant l'écran.
     *
     * @return array{token: string, fileSize: int, createdAt: string, expiresAt: string}|null
     */
    public function getCurrent(Anr $anr): ?array
    {
        $this->removeExpired();
        $paths = glob($this->getDirectory($anr) . '/*.pdf') ?: [];

        return $paths === [] ? null : $this->getMetadata(basename($paths[0], '.pdf'), $paths[0]);
    }

    /**
     * Supprime le rapport préparé : l'utilisateur y renonce.
     */
    public function discard(Anr $anr, string $token): void
    {
        if (preg_match('/^[0-9a-f]{32}$/', $token) === 1) {
            $path = $this->getPath($this->getDirectory($anr), $token);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Chemin du rapport préparé de l'analyse ; 412 s'il n'existe pas ou s'il a expiré.
     */
    public function getReportPath(Anr $anr, string $token): string
    {
        $path = preg_match('/^[0-9a-f]{32}$/', $token) === 1 ? $this->getPath($this->getDirectory($anr), $token) : null;
        if ($path === null || !is_file($path) || filemtime($path) + self::VALIDITY_IN_SECONDS < time()) {
            throw new Exception('Le rapport préparé n\'existe plus : préparez-le de nouveau.', 412);
        }

        return $path;
    }

    private function getDirectory(Anr $anr): string
    {
        return DATA_PATH . '/prepared-reports/' . $anr->getId();
    }

    private function getPath(string $directory, string $token): string
    {
        return $directory . '/' . $token . '.pdf';
    }

    /**
     * Supprime les rapports préparés expirés de toutes les analyses.
     */
    private function removeExpired(): void
    {
        foreach (glob(DATA_PATH . '/prepared-reports/*/*.pdf') ?: [] as $path) {
            if (filemtime($path) + self::VALIDITY_IN_SECONDS < time()) {
                unlink($path);
            }
        }
    }

    private function removeAll(string $directory): void
    {
        foreach (glob($directory . '/*.pdf') ?: [] as $path) {
            unlink($path);
        }
    }

    private function getMetadata(string $token, string $path): array
    {
        $createdAt = (new DateTimeImmutable())->setTimestamp(filemtime($path));

        return [
            'token' => $token,
            'fileSize' => filesize($path),
            'createdAt' => $createdAt->format(DateTimeInterface::ATOM),
            'expiresAt' => $createdAt->modify('+' . self::VALIDITY_IN_SECONDS . ' seconds')
                ->format(DateTimeInterface::ATOM),
        ];
    }
}
