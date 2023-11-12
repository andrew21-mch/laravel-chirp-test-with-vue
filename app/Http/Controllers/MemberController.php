<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index()
    {
        $groups = Group::all();
        return view('groups', [
            'groups' => $groups
        ]);
    }

    public function createGroup(){
        return view('create');
    }

}
