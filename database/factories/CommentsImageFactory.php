<?php

namespace Database\Factories;

use App\Models\CommentsImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentsImageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CommentsImage::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'comment_id'=> factoryAutoIncrementTweak(rand(1, 10)),
            'user_id'=>factoryAutoIncrementTweak(rand(1,10)),
            's3_filePath'=>'imgs1/1111111111.jpg',
            'tag_id'=>factoryAutoIncrementTweak(rand(1,12))
        ];
    }
}
