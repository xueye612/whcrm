<?php
// Permission hook
\think\Hook::add('check_auth', 'app\\common\\behavior\\AuthenticateBehavior');
