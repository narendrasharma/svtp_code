In bootstrap/app.php, register the `admin` middleware alias and Sanctum's
stateful middleware, e.g.:

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['admin' => \App\Http\Middleware\EnsureUserIsAdmin::class]);
        $middleware->statefulApi();
    })

And add a HandleInertiaRequests middleware (standard Inertia install step)
sharing auth + flash as props:

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => ['user' => $request->user()],
            'flash' => ['message' => fn () => $request->session()->get('flash')],
        ]);
    }
