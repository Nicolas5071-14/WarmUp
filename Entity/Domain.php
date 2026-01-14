<?php

namespace MauticPlugin\MauticWarmUpBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Domain
{
    private $id;
    private $domainName;
    private $emailPrefix;
    private $dailyLimit = 100;
    private $warmupPhase = 1;
    private $currentPhaseDay = 1;
    private $totalSentToday = 0;
    private $smtpHost;
    private $smtpPort = 587;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpEncryption = 'tls';
    private $isActive = false;
    private $isVerified = false;
    private $warmupStartDate;
    private $warmupEndDate;
    private $verificationDate;
    private $createdAt;
    private $updatedAt;
    private $campaigns;
    private $totalSent;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->campaigns = new ArrayCollection();
    }

    public function incrementTotalSentToday(int $count = 1): self
    {
        $this->totalSentToday += $count;
        return $this;
    }



    public function setTotalSentToday(int $totalSentToday): self
    {
        $this->totalSentToday = $totalSentToday;
        return $this;
    }

    public function incrementTotalSent(int $count = 1): self
    {
        $this->totalSent += $count;
        return $this;
    }



    public function setTotalSent(int $totalSent): self
    {
        $this->totalSent = $totalSent;
        return $this;
    }
    /**
     * Incrémenter le compteur total
     */
    // public function incrementTotalSent(int $count = 1): self
    // {
    //     $this->totalSent += $count;
    //     return $this;
    // }
    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('warmup_domains');
        $builder->setCustomRepositoryClass('MauticPlugin\MauticWarmUpBundle\Repository\DomainRepository');

        $builder->addId();

        $builder->createField('domainName', 'string')
            ->length(255)
            ->unique()
            ->build();

        $builder->createField('emailPrefix', 'string')
            ->length(100)
            ->nullable()
            ->build();

        $builder->createField('dailyLimit', 'integer')
            ->option('default', 100)
            ->build();

        $builder->createField('warmupPhase', 'integer')
            ->option('default', 1)
            ->build();

        $builder->createField('currentPhaseDay', 'integer')
            ->option('default', 1)
            ->build();

        $builder->createField('totalSentToday', 'integer')
            ->columnName('total_sent_today')
            ->option('default', 0)
            ->build();
        $builder->createField('totalSent', 'integer')
            ->columnName('total_sent')
            ->option('default', 0)
            ->build();

        $builder->createField('smtpHost', 'string')
            ->length(255)
            ->nullable()
            ->build();

        $builder->createField('smtpPort', 'integer')
            ->option('default', 587)
            ->build();

        $builder->createField('smtpUsername', 'string')
            ->length(255)
            ->nullable()
            ->build();

        $builder->createField('smtpPassword', 'string')
            ->length(255)
            ->nullable()
            ->build();

        $builder->createField('smtpEncryption', 'string')
            ->length(10)
            ->option('default', 'tls')
            ->build();

        $builder->createField('isActive', 'boolean')
            ->option('default', false)
            ->build();

        $builder->createField('isVerified', 'boolean')
            ->option('default', false)
            ->build();

        $builder->createField('warmupStartDate', 'datetime')
            ->nullable()
            ->build();

        $builder->createField('warmupEndDate', 'datetime')
            ->nullable()
            ->build();

        $builder->createField('verificationDate', 'datetime')
            ->nullable()
            ->build();

        $builder->addField('createdAt', 'datetime');
        $builder->addField('updatedAt', 'datetime');

        $builder->createOneToMany('campaigns', 'Campaign')
            ->mappedBy('domain')
            ->fetchExtraLazy()
            ->build();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomainName(): ?string
    {
        return $this->domainName;
    }

    public function setDomainName(string $domainName): self
    {
        $this->domainName = $domainName;
        return $this;
    }

    public function getEmailPrefix(): ?string
    {
        return $this->emailPrefix;
    }

    public function setEmailPrefix(?string $emailPrefix): self
    {
        $this->emailPrefix = $emailPrefix;
        return $this;
    }

    public function getDailyLimit(): int
    {
        return $this->dailyLimit;
    }

    public function setDailyLimit(int $dailyLimit): self
    {
        $this->dailyLimit = $dailyLimit;
        return $this;
    }

    public function getWarmupPhase(): int
    {
        return $this->warmupPhase;
    }

    public function setWarmupPhase(int $warmupPhase): self
    {
        $this->warmupPhase = $warmupPhase;
        return $this;
    }

    public function getCurrentPhaseDay(): int
    {
        return $this->currentPhaseDay;
    }

    public function setCurrentPhaseDay(int $currentPhaseDay): self
    {
        $this->currentPhaseDay = $currentPhaseDay;
        return $this;
    }

    public function getTotalSentToday(): int
    {
        return $this->totalSentToday;
    }



    public function incrementSentToday(): self
    {
        $this->totalSentToday++;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function resetDailyCounter(): self
    {
        $this->totalSentToday = 0;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSmtpHost(): ?string
    {
        return $this->smtpHost;
    }

    public function setSmtpHost(?string $smtpHost): self
    {
        $this->smtpHost = $smtpHost;
        return $this;
    }

    public function getSmtpPort(): int
    {
        return $this->smtpPort;
    }

    public function setSmtpPort(int $smtpPort): self
    {
        $this->smtpPort = $smtpPort;
        return $this;
    }

    public function getSmtpUsername(): ?string
    {
        return $this->smtpUsername;
    }

    public function setSmtpUsername(?string $smtpUsername): self
    {
        $this->smtpUsername = $smtpUsername;
        return $this;
    }

    public function getSmtpPassword(): ?string
    {
        return $this->smtpPassword;
    }

    public function setSmtpPassword(?string $smtpPassword): self
    {
        $this->smtpPassword = $smtpPassword;
        return $this;
    }

    public function getSmtpEncryption(): string
    {
        return $this->smtpEncryption;
    }

    public function setSmtpEncryption(string $smtpEncryption): self
    {
        $this->smtpEncryption = $smtpEncryption;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        if ($isVerified) {
            $this->verificationDate = new \DateTime();
        }
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getWarmupStartDate(): ?\DateTimeInterface
    {
        return $this->warmupStartDate;
    }

    public function setWarmupStartDate(?\DateTimeInterface $warmupStartDate): self
    {
        $this->warmupStartDate = $warmupStartDate;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getWarmupEndDate(): ?\DateTimeInterface
    {
        return $this->warmupEndDate;
    }

    public function setWarmupEndDate(?\DateTimeInterface $warmupEndDate): self
    {
        $this->warmupEndDate = $warmupEndDate;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getVerificationDate(): ?\DateTimeInterface
    {
        return $this->verificationDate;
    }

    public function setVerificationDate(?\DateTimeInterface $verificationDate): self
    {
        $this->verificationDate = $verificationDate;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection|Campaign[]
     */
    public function getCampaigns(): Collection
    {
        return $this->campaigns;
    }

    public function addCampaign(Campaign $campaign): self
    {
        if (!$this->campaigns->contains($campaign)) {
            $this->campaigns[] = $campaign;
            $campaign->setDomain($this);
        }

        return $this;
    }

    public function removeCampaign(Campaign $campaign): self
    {
        if ($this->campaigns->removeElement($campaign)) {
            // set the owning side to null (unless already changed)
            if ($campaign->getDomain() === $this) {
                $campaign->setDomain(null);
            }
        }

        return $this;
    }

    /**
     * Generate email address from prefix
     */
    public function generateEmailAddress(): ?string
    {
        if (!$this->emailPrefix || !$this->domainName) {
            return null;
        }
        return $this->emailPrefix . '@' . $this->domainName;
    }

    /**
     * Check if domain can send more emails today
     */
    public function canSendMoreToday(): bool
    {
        return $this->totalSentToday < $this->dailyLimit;
    }

    /**
     * Get remaining sends for today
     */
    public function getRemainingSendsToday(): int
    {
        return max(0, $this->dailyLimit - $this->totalSentToday);
    }

    /**
     * Calculate total sent (you might want to track this separately)
     */
    public function getTotalSent(): int
    {
        // This would need to be calculated from logs
        // For now, return today's count
        return $this->totalSentToday;
    }

    /**
     * Get the full SMTP configuration array
     */
    public function getSmtpConfig(): array
    {
        return [
            'host' => $this->smtpHost,
            'port' => $this->smtpPort,
            'username' => $this->smtpUsername,
            'password' => $this->smtpPassword,
            'encryption' => $this->smtpEncryption,
        ];
    }

    /**
     * Check if SMTP configuration is complete
     */
    public function hasCompleteSmtpConfig(): bool
    {
        return !empty($this->smtpHost) &&
            !empty($this->smtpUsername) &&
            !empty($this->smtpPassword);
    }

    /**
     * Get domain status as string
     */
    public function getStatus(): string
    {
        if (!$this->isActive) {
            return 'inactive';
        }

        if (!$this->isVerified) {
            return 'unverified';
        }

        if (!$this->canSendMoreToday()) {
            return 'limit_reached';
        }

        return 'active';
    }

    /**
     * Get domain status label
     */
    public function getStatusLabel(): string
    {
        $status = $this->getStatus();

        $labels = [
            'inactive' => 'Inactive',
            'unverified' => 'Unverified',
            'limit_reached' => 'Limit Reached',
            'active' => 'Active',
        ];

        return $labels[$status] ?? 'Unknown';
    }

    /**
     * Get domain status color (for UI)
     */
    public function getStatusColor(): string
    {
        $status = $this->getStatus();

        $colors = [
            'inactive' => 'default',
            'unverified' => 'warning',
            'limit_reached' => 'danger',
            'active' => 'success',
        ];

        return $colors[$status] ?? 'default';
    }

    /**
     * Check if domain is ready for sending
     */
    public function isReadyForSending(): bool
    {
        return $this->isActive &&
            $this->isVerified &&
            $this->canSendMoreToday() &&
            $this->hasCompleteSmtpConfig();
    }

    /**
     * Calculate warm-up progress percentage
     */
    public function getWarmupProgress(): float
    {
        if (!$this->warmupStartDate || !$this->warmupEndDate) {
            return 0;
        }

        $totalDays = $this->warmupStartDate->diff($this->warmupEndDate)->days;
        $daysPassed = $this->warmupStartDate->diff(new \DateTime())->days;

        if ($totalDays <= 0) {
            return 100;
        }

        return min(100, ($daysPassed / $totalDays) * 100);
    }

    /**
     * Check if warm-up is completed
     */
    public function isWarmupCompleted(): bool
    {
        return $this->getWarmupProgress() >= 100;
    }

    /**
     * Get current phase progress
     */
    public function getPhaseProgress(): float
    {
        // Assuming each phase has 10 days
        $daysPerPhase = 10;
        $daysInCurrentPhase = $this->currentPhaseDay;

        return min(100, ($daysInCurrentPhase / $daysPerPhase) * 100);
    }

    /**
     * Check if domain should progress to next phase
     */
    public function shouldProgressToNextPhase(): bool
    {
        // Progress to next phase when current phase is complete
        // and we haven't reached the maximum phase (assuming 10 phases)
        return $this->getPhaseProgress() >= 100 && $this->warmupPhase < 10;
    }

    /**
     * Progress to next warm-up phase
     */
    public function progressToNextPhase(): self
    {
        if ($this->shouldProgressToNextPhase()) {
            $this->warmupPhase++;
            $this->currentPhaseDay = 1;
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    /**
     * Increment current phase day
     */
    public function incrementPhaseDay(): self
    {
        $this->currentPhaseDay++;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    /**
     * Get domain display name (with prefix if available)
     */
    public function getDisplayName(): string
    {
        if ($this->emailPrefix) {
            return $this->emailPrefix . '@' . $this->domainName;
        }

        return $this->domainName;
    }

    /**
     * Get domain information for API/JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domainName' => $this->domainName,
            'emailPrefix' => $this->emailPrefix,
            'dailyLimit' => $this->dailyLimit,
            'totalSentToday' => $this->totalSentToday,
            'totalSent' => $this->totalSent,
            'remainingSendsToday' => $this->getRemainingSendsToday(),
            'isActive' => $this->isActive,
            'isVerified' => $this->isVerified,
            'warmupPhase' => $this->warmupPhase,
            'currentPhaseDay' => $this->currentPhaseDay,
            'warmupProgress' => $this->getWarmupProgress(),
            'phaseProgress' => $this->getPhaseProgress(),
            'status' => $this->getStatus(),
            'statusLabel' => $this->getStatusLabel(),
            'statusColor' => $this->getStatusColor(),
            'hasCompleteSmtpConfig' => $this->hasCompleteSmtpConfig(),
            'isReadyForSending' => $this->isReadyForSending(),
            'warmupStartDate' => $this->warmupStartDate ? $this->warmupStartDate->format('Y-m-d H:i:s') : null,
            'warmupEndDate' => $this->warmupEndDate ? $this->warmupEndDate->format('Y-m-d H:i:s') : null,
            'verificationDate' => $this->verificationDate ? $this->verificationDate->format('Y-m-d H:i:s') : null,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Validate domain configuration
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->domainName)) {
            $errors[] = 'Domain name is required';
        } elseif (!preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $this->domainName)) {
            $errors[] = 'Invalid domain name format';
        }

        if ($this->dailyLimit < 1) {
            $errors[] = 'Daily limit must be greater than 0';
        }

        if ($this->dailyLimit > 10000) {
            $errors[] = 'Daily limit cannot exceed 10,000';
        }

        if (!empty($this->emailPrefix) && !preg_match('/^[a-zA-Z0-9._-]+$/', $this->emailPrefix)) {
            $errors[] = 'Email prefix can only contain letters, numbers, dots, dashes and underscores';
        }

        if (!empty($this->smtpHost)) {
            if ($this->smtpPort < 1 || $this->smtpPort > 65535) {
                $errors[] = 'SMTP port must be between 1 and 65535';
            }

            if (!in_array($this->smtpEncryption, ['tls', 'ssl', ''], true)) {
                $errors[] = 'SMTP encryption must be tls, ssl, or empty';
            }
        }

        return $errors;
    }

    /**
     * Check if domain is valid for saving
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }
}