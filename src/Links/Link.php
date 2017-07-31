<?php

namespace Arbory\Base\Links;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    /**
     * @var array
     */
    protected $fillable = [
        'title',
        'new_tab',
        'href',
    ];

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->href;
    }

    /**
     * @return bool
     */
    public function isNewTab(): bool
    {
        return (bool) $this->new_tab;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->isNewTab() ? '_blank' : '';
    }

    /**
     * @return string
     */
    public function getAttributes()
    {
        $target = $this->getTarget();

        if( !$target )
        {
            return (string) null;
        }

        return 'target=\'' . $target . '\'';
    }
}
