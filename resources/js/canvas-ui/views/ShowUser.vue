<template>
    <section>
        <page-header />

        <div v-if="isReady" class="mt-5">
            <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1 col-md-12 mt-3">
                <div class="d-flex align-items-center justify-content-center">
                    <section>
                        <img
                            class="mr-2 ml-3 shadow-inner rounded-circle"
                            style="width: 150px"
                            :src="user.avatar || user.default_avatar"
                            :alt="user.name"
                        />
                    </section>
                    <section class="pl-3">
                        <h3 class="my-0">
                            {{ user.name }}
                        </h3>
                        <p v-if="user.summary" class="text-secondary my-1">
                            {{ user.summary }}
                        </p>
                        <p class="text-secondary mt-1 mb-0">
                            <a :href="`mailto:${user.email}`">{{ user.email }}</a>
                        </p>
                    </section>
                </div>

                <main role="main" class="mt-5">
                    <div>
                        <h4 class="my-4 border-bottom mt-5 pb-2">
                            <span class="border-bottom border-dark pb-2">Recent Posts</span>
                        </h4>

                        <div :key="`${index}-${post.id}`" v-for="(post, index) in posts">
                            <router-link
                                :to="{ name: 'show-post', params: { slug: post.slug } }"
                                class="text-decoration-none"
                            >
                                <div class="card mb-4 shadow">
                                    <div class="card-body px-0">
                                        <div class="container d-lg-inline-flex align-items-center">
                                            <div v-if="post.featured_image" class="col-12 col-lg-3 p-0">
                                                <img
                                                    :src="post.featured_image"
                                                    :alt="post.featured_image_caption"
                                                    class="rounded w-100"
                                                />
                                            </div>
                                            <section
                                                class="col-12 mt-3 mt-lg-0 px-0 px-lg-3"
                                                :class="post.featured_image ? 'col-lg-9' : ''"
                                            >
                                                <h5 class="card-title text-truncate mb-0">{{ post.title }}</h5>
                                                <p class="card-text text-truncate">{{ post.summary }}</p>
                                                <p class="card-text mb-0 text-secondary">
                                                    {{ post.user.name }}
                                                    <span v-if="post.topic.length"> in {{ post.topic[0].name }} </span>
                                                </p>
                                                <p class="card-text text-secondary">
                                                    {{ $moment(post.published_at).format('MMM D, Y') }} —
                                                    {{ post.read_time }}
                                                </p>
                                            </section>
                                        </div>
                                    </div>
                                </div>
                            </router-link>
                        </div>

                        <infinite-loading @infinite="fetchPosts">
                            <template #complete><span></span></template>
                            <template #error>
                                <div class="text-left">
                                    <div class="my-5">
                                        <p class="lead text-center text-muted mt-5">You have no published posts</p>
                                        <p class="lead text-center text-muted mt-1">
                                            Write on the go with our mobile-ready app!
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </infinite-loading>
                    </div>
                </main>
            </div>
        </div>
    </section>
</template>

<script>
import InfiniteLoading from 'v3-infinite-loading';
import 'v3-infinite-loading/lib/style.css';
import { useHead } from '@unhead/vue';
import { useRoute, useRouter } from 'vue-router';
import NProgress from 'nprogress';
import PageHeader from '../components/PageHeaderComponent.vue';
import isEmpty from 'lodash/isEmpty';
import { useCanvasUI } from '../composables/useCanvasUI';

export default {
    name: 'show-user',

    components: {
        InfiniteLoading,
        PageHeader,
    },

    setup() {
        const route = useRoute();
        const router = useRouter();
        const { request } = useCanvasUI();
        return { route, router, request };
    },

    data() {
        return {
            uri: this.route.params.id,
            user: null,
            posts: [],
            page: 1,
            isReady: false,
        };
    },

    watch: {
        user: {
            handler(newUser) {
                if (newUser) {
                    useHead({ title: newUser.name });
                }
            },
            immediate: true,
        },
    },

    async created() {
        await Promise.all([this.fetchUser(), this.fetchPosts()]);
        this.isReady = true;
        NProgress.done();
    },

    methods: {
        fetchUser() {
            return this.request()
                .get(`/api/users/${this.uri}`)
                .then(({ data }) => {
                    this.user = data;
                    NProgress.inc();
                })
                .catch(() => {
                    this.router.push({ name: 'posts' });
                });
        },

        fetchPosts($state) {
            if ($state) {
                return this.request()
                    .get(`/api/users/${this.uri}/posts`, {
                        params: {
                            page: this.page,
                        },
                    })
                    .then(({ data }) => {
                        if (!isEmpty(data) && !isEmpty(data.data)) {
                            this.page += 1;
                            this.posts.push(...data.data);

                            $state.loaded();
                        } else {
                            $state.complete();
                        }

                        if (isEmpty($state)) {
                            NProgress.inc();
                        }
                    })
                    .catch(() => {
                        NProgress.done();
                    });
            }
        },
    },
};
</script>
