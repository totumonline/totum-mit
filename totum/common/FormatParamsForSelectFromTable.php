<?php


namespace totum\common;

class FormatParamsForSelectFromTable
{
    /**
     * @var array
     */
    private $_where = [];
    /**
     * @var array
     */
    private $_order;
    /**
     * @var array
     */
    private $_field;

    public function where($name, $value, $operator = '=')
    {
        $this->_where[] = ['field' => $name, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    public function order($name, $direction = "asc")
    {
        $this->_order[] = ['field' => $name, 'ad' => $direction];
        return $this;
    }

    public function field($field)
    {
        $this->_field[] = $field;
        return $this;
    }

    /**
     * @return array
     */
    public function params(): array
    {
        return ['where' => $this->_where, 'order' => $this->_order, 'field' => $this->_field];
    }
}
