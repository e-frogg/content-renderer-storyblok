<?php


namespace Efrogg\ContentRenderer\Connector\Storyblok\Asset;


use Efrogg\ContentRenderer\Asset\Asset;

/**
 *
 * @property int $id => 2057145
 * @property string $alt => ""
 * @property string $name => ""
 * @property string $focus => "305x202:306x203"
 * @property string $title => ""
 * @property string $filename => "https://a.storyblok.com/f/106743/430x322/1741d89935/leadimage.png"
 * @property string $copyright => ""
 * @property string $fieldtype => "asset"
 */
class StoryBlokAsset extends Asset
{

    public function __construct(?array $data = null)
    {
        parent::__construct($data);
        $this->setSrc($this->computeSrc());
    }

    private function computeSrc(): string
    {
        // filename : https://a.storyblok.com/f/106743/430x322/3b44845347/leadimage.png;

        if(!empty($this->getParameters())) {
            $p = $this->getParameters();
            // parameters :
            //version:<long>
            //cache:<long>
            //download:0
            //width:200
            //height:<integer>
            //quality:<integer>
            //mode:<string>
            //      Crop
            //      CropUpsize
            //      Pad
            //      BoxPad
            //      Max
            //      Min
            //focusX:<float>
            //focusY:<float>
            //nofocus:<boolean>
            //force:<boolean>

            $parsed = parse_url($this->filename);
            $additionalPathParameters=[];
            if(isset($p['width']) || isset($p['height'])) {
                // resize
                if(isset($p['mode'])) {
                    try {
                        $additionalPathParameters[]=$this->convertMode($p['mode']);
                    } catch (InvalidResizeFormatException $e) {
                    }
                }
                $additionalPathParameters[]= ($p['width']??'0').'x'.($p['height']??'0');
                if(isset($this->focus)) {
                    $additionalPathParameters[]='filters:focal('.$this->focus.')';
                }
                // https://img2.storyblok.com/600x130/filters:focal(450x0:550x100)/f/39898/1000x600/d962430746/demo-image-human.jpeg
            }
            if(isset($p['quality'])) {
                $additionalPathParameters[]='filters:quality('.$p['quality'].')';
            }

            if(!empty($additionalPathParameters)) {
                $parsed['host']='img2.storyblok.com';
                $parsed['path']='/'.implode('/',$additionalPathParameters).$parsed['path'];
                return $parsed['scheme'].'://'.$parsed['host'].$parsed['path'];
            }
        }
        return $this->filename??'';
    }

    private function convertMode(string $mode): string
    {
        switch($mode) {
            case 'crop':
                return 'fit-in';
        }
        throw new InvalidResizeFormatException('invalid resize format for storyBlok : $mode');
    }
}