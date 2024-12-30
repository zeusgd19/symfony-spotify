<?php

namespace App\Entity;

use App\Repository\ArtistsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArtistsRepository::class)]
class Artist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: Song::class, mappedBy: 'artists')]
    private Collection $songs;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Playlist>
     */
    #[ORM\ManyToMany(targetEntity: Playlist::class)]
    private Collection $Playlist;

    public function __construct()
    {
        $this->Playlist = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSongs(): Collection
    {
        return $this->songs;
    }

    public function addSong(Song $song): self
    {
        if (!$this->songs->contains($song)) {
            $this->songs->add($song);
            $song->addArtist($this); // Actualiza la relación bidireccional
        }

        return $this;
    }

    public function removeSong(Song $song): self
    {
        if ($this->songs->removeElement($song)) {
            $song->removeArtist($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Playlist>
     */
    public function getPlaylist(): Collection
    {
        return $this->Playlist;
    }

    public function addPlaylist(Playlist $playlist): static
    {
        if (!$this->Playlist->contains($playlist)) {
            $this->Playlist->add($playlist);
        }

        return $this;
    }

    public function removePlaylist(Playlist $playlist): static
    {
        $this->Playlist->removeElement($playlist);

        return $this;
    }
}
