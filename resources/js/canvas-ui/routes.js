import AllPosts from './views/AllPosts.vue';
import AllTags from './views/AllTags.vue';
import AllTopics from './views/AllTopics.vue';
import ShowPost from './views/ShowPost.vue';
import ShowTag from './views/ShowTag.vue';
import ShowTopic from './views/ShowTopic.vue';
import ShowUser from './views/ShowUser.vue';

export default [
    {
        path: '/',
        name: 'posts',
        component: AllPosts,
    },
    {
        path: '/posts/:slug',
        name: 'show-post',
        component: ShowPost,
    },
    {
        path: '/tags',
        name: 'tags',
        component: AllTags,
    },
    {
        path: '/tags/:slug',
        name: 'show-tag',
        component: ShowTag,
    },
    {
        path: '/topics',
        name: 'topics',
        component: AllTopics,
    },
    {
        path: '/topics/:slug',
        name: 'show-topic',
        component: ShowTopic,
    },
    {
        path: '/:id',
        name: 'show-user',
        component: ShowUser,
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'catch-all',
        redirect: '/',
    },
];
