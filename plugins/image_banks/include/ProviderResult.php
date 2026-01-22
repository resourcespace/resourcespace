<?php
namespace ImageBanks;

class ProviderResult
    {
    private $id;
    private Provider $provider;
    private string $title = "";

    protected $original_file_url;
    protected string $provider_url;

    protected $preview_url;
    protected int $preview_width;
    protected int $preview_height;

    public function __construct($id, Provider $provider)
        {
        $this->id = $id;
        $this->provider = $provider;
        return $this;
        }

    /** Id getter */
    public function getId()
        {
        return $this->id;
        }

    /** Provider getter */
    public function getProvider(): Provider
        {
        return $this->provider;
        }

    /** Original file URL setter */
    public function setOriginalFileUrl(string $url): self
        {
        $this->original_file_url = $url;
        return $this;
        }

    /** Original file URL getter */
    public function getOriginalFileUrl(): ?string
        {
        return $this->original_file_url;
        }

    /** Preview URL setter */
    public function setPreviewUrl(string $url): self
        {
        $this->preview_url = $url;
        return $this;
        }

    /** Preview URL getter */
    public function getPreviewUrl(): ?string
        {
        return $this->preview_url;
        }

    /** Provider URL setter */
    public function setProviderUrl(string $url): self
        {
        $this->provider_url = $url;
        return $this;
        }

    /** Provider URL getter */
    public function getProviderUrl(): string
        {
        return $this->provider_url;
        }

    /** Preview width setter */
    public function setPreviewWidth(int $width): self
        {
        $this->preview_width = $width;
        return $this;
        }

    /** Preview width getter */
    public function getPreviewWidth(): int
        {
        return $this->preview_width;
        }

    /** Preview height setter */
    public function setPreviewHeight(int $height): self
        {
        $this->preview_height = $height;
        return $this;
        }

    /** Preview height getter */
    public function getPreviewHeight(): int
        {
        return $this->preview_height;
        }

    /** Title setter */
    public function setTitle(string $title): self
        {
        $this->title = trim($title);
        return $this;
        }

    /** Title getter */
    public function getTitle(): string
        {
        return $this->title;
        }
    }