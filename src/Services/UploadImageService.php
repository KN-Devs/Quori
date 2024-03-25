<?php 

namespace App\Services;

use Symfony\Component\Filesystem\Filesystem;

class UploadImageService {

    public function __construct(private string $profileFolder, private string $profileFolderPublic, private Filesystem $fs)
    {
        
    }

    public function uploadProfileImage($picture, $oldPicture = null)
    {
        
        $ext = $picture->guessExtension() ?? 'bin';
        $fileName = bin2hex(random_bytes(10)) . '.' . $ext;
        $picture->move($this->profileFolder, $fileName);

        if ($oldPicture) {
            $this->fs->remove($this->profileFolder . "/" . pathinfo($oldPicture, PATHINFO_BASENAME));
        }

        return $this->profileFolderPublic . "/" . $fileName;
    }
}

?>
