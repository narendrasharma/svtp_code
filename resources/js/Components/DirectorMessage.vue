<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

const directorImage = appUrl('/team/kanhaiya.jpeg');
</script>

<template>
    <section class="director-message-section py-5">
        <div class="container">
            <div class="director-card">
                <div class="director-content">
                    <div class="section-kicker">
                        <span class="kicker-line"></span>
                        <span>CEO's Message</span>
                    </div>

                    <h2 class="director-heading">
                        Your Journey to Braj,
                        <span>Planned With Local Understanding</span>
                    </h2>

                    <p class="radhe-greeting">राधे राधे 🙏</p>

                    <p>
                        Welcome to <strong>Shree Vrindavan Tour Packages</strong>.
                        For us, a journey to Mathura, Vrindavan and the sacred land
                        of Braj is more than sightseeing — it is an experience
                        connected with faith, culture and memories that stay with you.
                    </p>

                    <p>
                        Being locally connected to Braj, we understand temple timings,
                        routes, important places and the practical details that can make
                        a pilgrimage comfortable and peaceful. Whether you are travelling
                        with family, senior citizens, friends or visiting Braj for the
                        first time, our aim is to help you experience the journey at
                        your own pace.
                    </p>

                    <p>
                        From Vrindavan and Mathura to Gokul, Govardhan, Barsana and
                        Nandgaon, we help plan your route, transportation and itinerary
                        according to your requirements.
                    </p>

                    <blockquote class="director-quote">
                        “Come as a traveller. Experience Braj as a devotee.”
                    </blockquote>

                    <div class="director-footer">
                        <div class="director-signature">
                            <strong>Kanhaiya Upadhyay</strong>
                            <span>CEO</span>
                            <small>Shree Vrindavan Tour Packages</small>
                        </div>

                        <div class="director-actions">
                            <Link :href="appUrl('/packages')" class="btn btn-outline-svtp">
                                Explore Tours
                            </Link>

                            <a
                                href="#quick-enquiry"
                                class="btn btn-svtp"
                            >
                                Plan Your Trip
                            </a>
                        </div>
                    </div>
                </div>

                <div class="director-photo-wrap">
                    <div class="decor-circle decor-circle-one"></div>
                    <div class="decor-circle decor-circle-two"></div>

                    <div class="photo-frame">
                        <img
                            :src="directorImage"
                            alt="Kanhaiya Upadhyay - Director, Shree Vrindavan Tour Packages"
                            loading="lazy"
                        />

                        <div class="photo-name-card">
                            <strong>Kanhaiya Upadhyay</strong>
                            <span>CEO</span>
                        </div>
                    </div>

                    <div class="local-trust-badge">
                        <i class="bi bi-geo-alt-fill"></i>
                        <div>
                            <strong>Local Braj Expertise</strong>
                            <span>Mathura · Vrindavan · Braj</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.director-message-section {
    position: relative;
    padding: 78px 0;
    overflow: hidden;
    background:
        radial-gradient(circle at 8% 15%, rgba(255, 183, 197, 0.18), transparent 28%),
        radial-gradient(circle at 92% 85%, rgba(244, 181, 71, 0.16), transparent 28%),
        #fff8ee;
}

.director-card {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.65fr);
    gap: 55px;
    align-items: center;
    padding: 54px;
    border: 1px solid rgba(111, 22, 61, 0.10);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.88);
    box-shadow: 0 22px 55px rgba(80, 25, 45, 0.09);
}

.director-card::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 5px;
    border-radius: 28px 0 0 28px;
    background: linear-gradient(#7a1538, #ff5b8d, #efa83e);
}

.section-kicker {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 13px;
    color: #b03a62;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.kicker-line {
    display: inline-block;
    width: 35px;
    height: 1px;
    background: #d28a35;
}

.director-heading {
    max-width: 720px;
    margin-bottom: 18px;
    color: #641833;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: clamp(2rem, 3vw, 3.1rem);
    line-height: 1.08;
}

.director-heading span {
    display: block;
    color: #9b3154;
}

.radhe-greeting {
    margin-bottom: 13px;
    color: #c14f68;
    font-size: 1.15rem;
    font-weight: 700;
}

.director-content > p:not(.radhe-greeting) {
    max-width: 760px;
    margin-bottom: 14px;
    color: #62565a;
    font-size: 0.98rem;
    line-height: 1.8;
}

.director-content strong {
    color: #651c38;
}

.director-quote {
    position: relative;
    margin: 24px 0 26px;
    padding: 16px 20px;
    border-left: 3px solid #e49b36;
    border-radius: 0 12px 12px 0;
    background: #fff5e8;
    color: #7c2042;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.08rem;
    font-style: italic;
}

.director-footer {
    display: flex;
    gap: 28px;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
}

.director-signature {
    display: flex;
    flex-direction: column;
}

.director-signature strong {
    color: #6b1736;
    font-size: 1.15rem;
}

.director-signature span {
    color: #d47743;
    font-weight: 600;
}

.director-signature small {
    margin-top: 2px;
    color: #837278;
}

.director-actions {
    display: flex;
    gap: 11px;
    flex-wrap: wrap;
}

.btn-svtp {
    border: 0;
    border-radius: 999px;
    padding: 11px 21px;
    background: linear-gradient(90deg, #ff7029, #f33982);
    box-shadow: 0 8px 20px rgba(235, 58, 108, 0.18);
    color: #fff;
    font-weight: 600;
}

.btn-svtp:hover {
    color: #fff;
    transform: translateY(-1px);
}

.btn-outline-svtp {
    border: 1px solid #c97847;
    border-radius: 999px;
    padding: 10px 20px;
    background: transparent;
    color: #781d3e;
    font-weight: 600;
}

.btn-outline-svtp:hover {
    background: #fff1df;
    color: #781d3e;
}

.director-photo-wrap {
    position: relative;
    min-height: 440px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-frame {
    position: relative;
    z-index: 2;
    width: min(100%, 355px);
    overflow: hidden;
    border: 8px solid #fff;
    border-radius: 28px 28px 75px 28px;
    background: #fff;
    box-shadow: 0 25px 50px rgba(82, 23, 44, 0.18);
}

.photo-frame img {
    display: block;
    width: 100%;
    height: 410px;
    object-fit: cover;
    object-position: center;
}

.photo-name-card {
    position: absolute;
    right: 16px;
    bottom: 16px;
    left: 16px;
    display: flex;
    flex-direction: column;
    padding: 14px 18px;
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 15px;
    background: rgba(74, 16, 43, 0.88);
    backdrop-filter: blur(8px);
    color: #fff;
}

.photo-name-card strong {
    font-size: 1rem;
}

.photo-name-card span {
    color: #ffd5a4;
    font-size: 0.84rem;
}

.local-trust-badge {
    position: absolute;
    z-index: 4;
    right: -10px;
    bottom: 7px;
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 215px;
    padding: 12px 15px;
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 14px 30px rgba(79, 23, 42, 0.16);
}

.local-trust-badge i {
    display: grid;
    width: 39px;
    height: 39px;
    place-items: center;
    border-radius: 50%;
    background: #fff0df;
    color: #d66938;
    font-size: 1.05rem;
}

.local-trust-badge div {
    display: flex;
    flex-direction: column;
}

.local-trust-badge strong {
    color: #6b1936;
    font-size: 0.87rem;
}

.local-trust-badge span {
    color: #84757a;
    font-size: 0.73rem;
}

.decor-circle {
    position: absolute;
    border-radius: 50%;
}

.decor-circle-one {
    width: 270px;
    height: 270px;
    top: 8px;
    right: 0;
    border: 1px dashed rgba(203, 91, 117, 0.38);
}

.decor-circle-two {
    width: 170px;
    height: 170px;
    left: 5px;
    bottom: 28px;
    background: rgba(239, 168, 62, 0.09);
}

@media (max-width: 991.98px) {
    .director-card {
        grid-template-columns: 1fr;
        padding: 38px;
    }

    .director-photo-wrap {
        order: -1;
        min-height: auto;
    }

    .photo-frame {
        width: min(100%, 520px);
    }

    .photo-frame img {
        height: auto;
        object-fit: contain;
    }

    .photo-name-card {
        position: static;
        margin: 0;
        border-radius: 0 0 18px 18px;
    }

    .local-trust-badge {
        position: relative;
        right: auto;
        bottom: auto;
        width: min(calc(100% - 30px), 430px);
        margin: -10px auto 0;
    }
}

@media (max-width: 575.98px) {
    .director-message-section {
        padding: 48px 0;
    }

    .director-card {
        gap: 30px;
        padding: 25px 20px;
        border-radius: 20px;
    }

    .director-heading {
        font-size: 2rem;
    }

    .local-trust-badge {
        width: calc(100% - 30px);
        margin: -15px auto 0;
    }

    .director-footer,
    .director-actions {
        align-items: stretch;
    }

    .director-actions {
        width: 100%;
    }

    .director-actions .btn {
        flex: 1;
        text-align: center;
    }
}

@media (max-width: 399.98px) {
    .director-card {
        padding-inline: 16px;
    }

    .director-heading {
        font-size: 1.75rem;
    }

    .director-actions {
        flex-direction: column;
    }
}
</style>
